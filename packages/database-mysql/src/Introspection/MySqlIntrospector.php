<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

readonly class MySqlIntrospector implements IntrospectorInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $database,
    ) {}

    /**
     * @return array<string>
     */
    public function getTables(): array
    {
        $sql = <<<'SQL'
            SELECT TABLE_NAME
            FROM information_schema.tables
            WHERE TABLE_SCHEMA = ?
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        SQL;

        $rows = $this->connection->query($sql, [$this->database]);

        return array_column($rows, 'TABLE_NAME');
    }

    public function getTable(
        string $name,
    ): ?Table {
        if (!$this->tableExists($name)) {
            return null;
        }

        $columns = $this->getColumns($name);
        $indexes = $this->getIndexes($name);

        return new Table(
            name: $name,
            columns: $columns,
            indexes: $indexes,
        );
    }

    public function tableExists(
        string $name,
    ): bool {
        return in_array($name, $this->getTables(), true);
    }

    /**
     * @return array<Column>
     */
    public function getColumns(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                COLUMN_NAME,
                DATA_TYPE,
                CHARACTER_MAXIMUM_LENGTH,
                IS_NULLABLE,
                COLUMN_DEFAULT,
                EXTRA
            FROM information_schema.columns
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        SQL;

        $rows = $this->connection->query($sql, [$this->database, $table]);
        $columns = [];

        foreach ($rows as $row) {
            $length = $row['CHARACTER_MAXIMUM_LENGTH'] !== null
                ? (int) $row['CHARACTER_MAXIMUM_LENGTH']
                : null;

            $columns[] = new Column(
                name: $row['COLUMN_NAME'],
                type: $row['DATA_TYPE'],
                length: $length,
                nullable: $row['IS_NULLABLE'] === 'YES',
                default: $row['COLUMN_DEFAULT'],
                autoIncrement: str_contains($row['EXTRA'], 'auto_increment'),
            );
        }

        return $columns;
    }

    /**
     * @return array<Index>
     */
    public function getIndexes(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                INDEX_NAME,
                COLUMN_NAME,
                NON_UNIQUE,
                INDEX_TYPE,
                SEQ_IN_INDEX
            FROM information_schema.statistics
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            ORDER BY INDEX_NAME, SEQ_IN_INDEX
        SQL;

        $rows = $this->connection->query($sql, [$this->database, $table]);

        // Group columns by index name
        $indexData = [];
        foreach ($rows as $row) {
            $indexName = $row['INDEX_NAME'];
            if (!isset($indexData[$indexName])) {
                $indexData[$indexName] = [
                    'columns' => [],
                    'non_unique' => $row['NON_UNIQUE'],
                    'type' => $row['INDEX_TYPE'],
                ];
            }
            $indexData[$indexName]['columns'][] = $row['COLUMN_NAME'];
        }

        // Convert to Index objects (exclude PRIMARY key from regular indexes)
        $indexes = [];
        foreach ($indexData as $name => $data) {
            if ($name === 'PRIMARY') {
                continue;
            }

            $type = $this->mapIndexType($data['type'], (string) $data['non_unique']);
            $indexes[] = new Index(
                name: $name,
                columns: $data['columns'],
                type: $type,
            );
        }

        return $indexes;
    }

    /**
     * @return array<ForeignKey>
     */
    public function getForeignKeys(
        string $table,
    ): array {
        // Get foreign key columns
        $sql = <<<'SQL'
            SELECT
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME,
                ORDINAL_POSITION
            FROM information_schema.key_column_usage
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
        SQL;

        $keyRows = $this->connection->query($sql, [$this->database, $table]);

        if (empty($keyRows)) {
            return [];
        }

        // Get referential constraints for ON DELETE/UPDATE actions
        $constraintSql = <<<'SQL'
            SELECT
                CONSTRAINT_NAME,
                DELETE_RULE,
                UPDATE_RULE
            FROM information_schema.referential_constraints
            WHERE CONSTRAINT_SCHEMA = ?
            AND TABLE_NAME = ?
        SQL;

        $constraintRows = $this->connection->query($constraintSql, [$this->database, $table]);

        // Index constraints by name
        $constraints = [];
        foreach ($constraintRows as $row) {
            $constraints[$row['CONSTRAINT_NAME']] = [
                'onDelete' => $row['DELETE_RULE'],
                'onUpdate' => $row['UPDATE_RULE'],
            ];
        }

        // Group key columns by constraint name
        $fkData = [];
        foreach ($keyRows as $row) {
            $constraintName = $row['CONSTRAINT_NAME'];
            if (!isset($fkData[$constraintName])) {
                $fkData[$constraintName] = [
                    'columns' => [],
                    'referencedTable' => $row['REFERENCED_TABLE_NAME'],
                    'referencedColumns' => [],
                ];
            }
            $fkData[$constraintName]['columns'][] = $row['COLUMN_NAME'];
            $fkData[$constraintName]['referencedColumns'][] = $row['REFERENCED_COLUMN_NAME'];
        }

        // Convert to ForeignKey objects
        $foreignKeys = [];
        foreach ($fkData as $name => $data) {
            $constraint = $constraints[$name] ?? [];
            $foreignKeys[] = new ForeignKey(
                name: $name,
                columns: $data['columns'],
                referencedTable: $data['referencedTable'],
                referencedColumns: $data['referencedColumns'],
                onDelete: $constraint['onDelete'] ?? null,
                onUpdate: $constraint['onUpdate'] ?? null,
            );
        }

        return $foreignKeys;
    }

    /**
     * @return array<string>
     */
    public function getPrimaryKey(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                COLUMN_NAME
            FROM information_schema.statistics
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND INDEX_NAME = 'PRIMARY'
            ORDER BY SEQ_IN_INDEX
        SQL;

        $rows = $this->connection->query($sql, [$this->database, $table]);

        return array_column($rows, 'COLUMN_NAME');
    }

    private function mapIndexType(
        string $mysqlType,
        string $nonUnique,
    ): IndexType {
        if ($mysqlType === 'FULLTEXT') {
            return IndexType::Fulltext;
        }

        if ($nonUnique === '0') {
            return IndexType::Unique;
        }

        return IndexType::Btree;
    }
}
