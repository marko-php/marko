<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

class PgSqlIntrospector implements IntrospectorInterface
{
    private const string DEFAULT_SCHEMA = 'public';

    /**
     * PostgreSQL to normalized type mapping.
     *
     * @var array<string, string>
     */
    private const array TYPE_MAP = [
        'integer' => 'integer',
        'int4' => 'integer',
        'bigint' => 'bigint',
        'int8' => 'bigint',
        'smallint' => 'smallint',
        'int2' => 'smallint',
        'character varying' => 'varchar',
        'varchar' => 'varchar',
        'character' => 'char',
        'char' => 'char',
        'text' => 'text',
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'timestamp without time zone' => 'timestamp',
        'timestamp' => 'timestamp',
        'timestamp with time zone' => 'timestamptz',
        'timestamptz' => 'timestamptz',
        'date' => 'date',
        'time without time zone' => 'time',
        'time' => 'time',
        'time with time zone' => 'timetz',
        'timetz' => 'timetz',
        'numeric' => 'decimal',
        'decimal' => 'decimal',
        'real' => 'float',
        'float4' => 'float',
        'double precision' => 'double',
        'float8' => 'double',
        'json' => 'json',
        'jsonb' => 'jsonb',
        'uuid' => 'uuid',
        'bytea' => 'blob',
    ];

    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $schema = self::DEFAULT_SCHEMA,
    ) {}

    public function getTables(): array
    {
        $sql = <<<'SQL'
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = ?
              AND table_type = 'BASE TABLE'
            ORDER BY table_name
            SQL;

        $rows = $this->connection->query($sql, [$this->schema]);

        return array_column($rows, 'table_name');
    }

    public function getTable(
        string $name,
    ): ?Table {
        $columns = $this->getColumns($name);

        if (count($columns) === 0) {
            return null;
        }

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
        $sql = <<<'SQL'
            SELECT table_name
            FROM information_schema.tables
            WHERE table_name = ?
              AND table_schema = ?
              AND table_type = 'BASE TABLE'
            LIMIT 1
            SQL;

        $rows = $this->connection->query($sql, [$name, $this->schema]);

        return count($rows) > 0;
    }

    public function getColumns(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                column_name,
                data_type,
                character_maximum_length,
                is_nullable,
                column_default,
                is_identity,
                identity_generation
            FROM information_schema.columns
            WHERE table_name = ?
              AND table_schema = ?
            ORDER BY ordinal_position
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        $primaryKeyColumns = $this->getPrimaryKeyColumnsSet($table);
        $uniqueColumns = $this->getUniqueConstraintColumnsSet($table);

        $columns = [];
        foreach ($rows as $row) {
            $columnName = $row['column_name'];
            $type = $this->mapType($row['data_type']);
            $length = $row['character_maximum_length'] !== null ? (int) $row['character_maximum_length'] : null;
            $nullable = $row['is_nullable'] === 'YES';
            $isIdentity = $row['is_identity'] === 'YES';
            $isSerial = $this->isSequenceDefault($row['column_default']);
            $autoIncrement = $isIdentity || $isSerial;
            $default = $autoIncrement ? null : $this->parseDefault($row['column_default'], $type);
            $isPrimaryKey = isset($primaryKeyColumns[$columnName]);
            $isUnique = isset($uniqueColumns[$columnName]) && !$isPrimaryKey;

            $columns[] = new Column(
                name: $columnName,
                type: $type,
                length: $length,
                nullable: $nullable,
                default: $default,
                unique: $isUnique,
                primaryKey: $isPrimaryKey,
                autoIncrement: $autoIncrement,
            );
        }

        return $columns;
    }

    public function getIndexes(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = ?
              AND schemaname = ?
            ORDER BY indexname
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        $indexes = [];
        foreach ($rows as $row) {
            $name = $row['indexname'];
            $indexDef = $row['indexdef'];

            $isUnique = str_contains($indexDef, 'UNIQUE INDEX');
            $columns = $this->parseIndexColumns($indexDef);

            $indexes[] = new Index(
                name: $name,
                columns: $columns,
                type: $isUnique ? IndexType::Unique : IndexType::Btree,
            );
        }

        return $indexes;
    }

    public function getForeignKeys(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                tc.constraint_name,
                kcu.column_name,
                ccu.table_name AS referenced_table,
                ccu.column_name AS referenced_column,
                rc.delete_rule,
                rc.update_rule
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
                AND ccu.table_schema = tc.table_schema
            JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
                AND rc.constraint_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_name = ?
              AND tc.table_schema = ?
            ORDER BY tc.constraint_name, kcu.ordinal_position
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        // Group by constraint name for multi-column foreign keys
        $grouped = [];
        foreach ($rows as $row) {
            $constraintName = $row['constraint_name'];
            if (!isset($grouped[$constraintName])) {
                $grouped[$constraintName] = [
                    'columns' => [],
                    'referenced_columns' => [],
                    'referenced_table' => $row['referenced_table'],
                    'delete_rule' => $row['delete_rule'],
                    'update_rule' => $row['update_rule'],
                ];
            }
            $grouped[$constraintName]['columns'][] = $row['column_name'];
            $grouped[$constraintName]['referenced_columns'][] = $row['referenced_column'];
        }

        $foreignKeys = [];
        foreach ($grouped as $constraintName => $data) {
            $foreignKeys[] = new ForeignKey(
                name: $constraintName,
                columns: $data['columns'],
                referencedTable: $data['referenced_table'],
                referencedColumns: $data['referenced_columns'],
                onDelete: $data['delete_rule'],
                onUpdate: $data['update_rule'],
            );
        }

        return $foreignKeys;
    }

    public function getPrimaryKey(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_class c ON c.oid = i.indrelid
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_constraint con ON con.conindid = i.indexrelid
            WHERE con.contype = 'p'
              AND c.relname = ?
              AND n.nspname = ?
            ORDER BY array_position(i.indkey, a.attnum)
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        return array_column($rows, 'column_name');
    }

    /**
     * Get primary key columns as a set for fast lookup.
     *
     * @return array<string, true>
     */
    private function getPrimaryKeyColumnsSet(
        string $table,
    ): array {
        $columns = $this->getPrimaryKey($table);

        return array_fill_keys($columns, true);
    }

    /**
     * Get unique constraint columns as a set for fast lookup.
     *
     * @return array<string, true>
     */
    private function getUniqueConstraintColumnsSet(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT a.attname AS column_name
            FROM pg_index i
            JOIN pg_class c ON c.oid = i.indrelid
            JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_constraint con ON con.conindid = i.indexrelid
            WHERE con.contype = 'u'
              AND c.relname = ?
              AND n.nspname = ?
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        return array_fill_keys(array_column($rows, 'column_name'), true);
    }

    private function mapType(
        string $pgType,
    ): string {
        return self::TYPE_MAP[$pgType] ?? $pgType;
    }

    private function isSequenceDefault(
        ?string $default,
    ): bool {
        if ($default === null) {
            return false;
        }

        return str_contains($default, 'nextval(') && str_contains($default, '_seq');
    }

    private function parseDefault(
        ?string $default,
        string $type,
    ): mixed {
        if ($default === null) {
            return null;
        }

        // Remove type cast suffix like ::character varying, ::integer, etc.
        $default = preg_replace('/::[\w\s]+$/', '', $default);

        // Handle string defaults (wrapped in quotes)
        if (preg_match("/^'(.*)'/", $default, $matches)) {
            return $matches[1];
        }

        // Handle boolean defaults
        if ($type === 'boolean') {
            if ($default === 'true') {
                return true;
            }
            if ($default === 'false') {
                return false;
            }
        }

        // Handle numeric defaults
        if (in_array($type, ['integer', 'bigint', 'smallint'], true)) {
            if (is_numeric($default)) {
                return (int) $default;
            }
        }

        if (in_array($type, ['decimal', 'float', 'double'], true)) {
            if (is_numeric($default)) {
                return (float) $default;
            }
        }

        // Return as-is for expressions like CURRENT_TIMESTAMP, NOW(), etc.
        return $default;
    }

    /**
     * Parse column names from an index definition.
     *
     * @return array<string>
     */
    private function parseIndexColumns(
        string $indexDef,
    ): array {
        // Match the columns inside parentheses after USING btree/hash/etc.
        if (preg_match('/USING\s+\w+\s*\(([^)]+)\)/i', $indexDef, $matches)) {
            $columnsPart = $matches[1];

            // Split by comma and clean up each column name
            $columns = array_map(
                fn (string $col): string => trim(
                    preg_replace('/\s+(ASC|DESC|NULLS\s+(FIRST|LAST))$/i', '', trim($col)),
                ),
                explode(',', $columnsPart),
            );

            return array_values(array_filter($columns));
        }

        return [];
    }
}
