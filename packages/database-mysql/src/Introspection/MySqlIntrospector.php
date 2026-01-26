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

        $primaryKeyColumns = $this->getPrimaryKey($name);
        $uniqueColumns = $this->getUniqueColumns($name);
        // Set unique=true on columns, but keep indexes (don't filter)
        // The diff calculator will handle matching column unique=true with indexes
        $columns = $this->getColumns($name, $primaryKeyColumns, $uniqueColumns);
        $indexes = $this->getIndexes($name, []);  // Don't filter any indexes
        $foreignKeys = $this->getForeignKeys($name);

        return new Table(
            name: $name,
            columns: $columns,
            indexes: $indexes,
            foreignKeys: $foreignKeys,
        );
    }

    public function tableExists(
        string $name,
    ): bool {
        return in_array($name, $this->getTables(), true);
    }

    /**
     * @param array<string> $primaryKeyColumns
     * @param array<string> $uniqueColumns
     * @return array<Column>
     */
    public function getColumns(
        string $table,
        array $primaryKeyColumns = [],
        array $uniqueColumns = [],
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
            $columnName = $row['COLUMN_NAME'];
            $length = $row['CHARACTER_MAXIMUM_LENGTH'] !== null
                ? (int) $row['CHARACTER_MAXIMUM_LENGTH']
                : null;

            $isPrimaryKey = in_array($columnName, $primaryKeyColumns, true);
            $isUnique = in_array($columnName, $uniqueColumns, true);

            $columns[] = new Column(
                name: $columnName,
                type: strtoupper($row['DATA_TYPE']),
                length: $length,
                nullable: $row['IS_NULLABLE'] === 'YES',
                default: $row['COLUMN_DEFAULT'],
                unique: $isUnique,
                primaryKey: $isPrimaryKey,
                autoIncrement: str_contains($row['EXTRA'], 'auto_increment'),
            );
        }

        return $columns;
    }

    /**
     * Get columns that have single-column unique indexes.
     *
     * @return array<string>
     */
    private function getUniqueColumns(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT COLUMN_NAME, INDEX_NAME
            FROM information_schema.statistics
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND NON_UNIQUE = 0
            AND INDEX_NAME != 'PRIMARY'
        SQL;

        $rows = $this->connection->query($sql, [$this->database, $table]);

        // Group by index to find single-column unique indexes
        $indexColumns = [];
        foreach ($rows as $row) {
            $indexName = $row['INDEX_NAME'];
            $indexColumns[$indexName][] = $row['COLUMN_NAME'];
        }

        // Only return columns that are the sole column in a unique index
        $uniqueColumns = [];
        foreach ($indexColumns as $columns) {
            if (count($columns) === 1) {
                $uniqueColumns[] = $columns[0];
            }
        }

        return $uniqueColumns;
    }

    /**
     * @param array<string> $uniqueColumns Columns that have single-column unique indexes
     *                                     (these are represented by the column's unique property)
     * @return array<Index>
     */
    public function getIndexes(
        string $table,
        array $uniqueColumns = [],
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

            // Skip single-column unique indexes - these are represented by the
            // column's unique property instead, to match how entities define them
            if (
                (string) $data['non_unique'] === '0'
                && count($data['columns']) === 1
                && in_array($data['columns'][0], $uniqueColumns, true)
            ) {
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
