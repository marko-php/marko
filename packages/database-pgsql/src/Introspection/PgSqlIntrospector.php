<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\PgSql\Sql\PgSqlIdentifier;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;

readonly class PgSqlIntrospector implements IntrospectorInterface, ExpressionDefaultMatcherInterface
{
    private const string DEFAULT_SCHEMA = 'public';

    /** pg_constraint.confdeltype and confupdtype codes mapped to the rule names SQL uses */
    private const array FOREIGN_KEY_RULES = [
        'a' => 'NO ACTION',
        'r' => 'RESTRICT',
        'c' => 'CASCADE',
        'n' => 'SET NULL',
        'd' => 'SET DEFAULT',
    ];

    /**
     * The temporary table and column matchesStoredDefault() declares an expression default on.
     */
    private const string DEFAULT_PROBE_TABLE = 'marko_default_probe';

    private const string DEFAULT_PROBE_COLUMN = 'probe';

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
        'jsonb' => 'json',
        'uuid' => 'uuid',
        'bytea' => 'blob',
    ];

    public function __construct(
        private ConnectionInterface $connection,
        private string $schema = self::DEFAULT_SCHEMA,
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
            $autoIncrement = $isIdentity
                || $isSerial;
            $default = $autoIncrement ? null : $this->parseDefault($row['column_default'], $type);
            $isPrimaryKey = isset($primaryKeyColumns[$columnName]);
            $isUnique = isset($uniqueColumns[$columnName])
                && !$isPrimaryKey;

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

    /**
     * Whether the column would report the default it has now if it were declared with $expression.
     *
     * Inside a transaction that is always rolled back, it creates a temporary table with one column of the real
     * column's type and the expression as its default, then compares the default PostgreSQL stored for it with
     * the real column's. Both are deparsed by pg_get_expr(), as information_schema.columns.column_default
     * reports them, so `now() + interval '1 day'` matches a stored `(now() + '1 day'::interval)`.
     *
     * @throws ExpressionDefaultProbeException When PostgreSQL rejects the expression, or the user may not create a temporary table
     */
    public function matchesStoredDefault(
        string $table,
        string $column,
        Expression $expression,
    ): bool {
        $relation = $this->quoteIdentifier($this->schema) . '.' . $this->quoteIdentifier($table);
        $stored = $this->rawColumnDefault($relation, $column);

        if (
            $stored === null
            || $stored['column_default'] === null
        ) {
            return false;
        }

        $transaction = $this->connection instanceof TransactionInterface ? $this->connection : null;
        $transaction?->beginTransaction();

        try {
            $this->createDefaultProbe($table, $column, $stored['column_type'], $expression);
            $probe = $this->rawColumnDefault('pg_temp.' . self::DEFAULT_PROBE_TABLE, self::DEFAULT_PROBE_COLUMN);
        } finally {
            if ($transaction !== null) {
                $transaction->rollback();
            } else {
                $this->connection->execute(
                    'DROP TABLE IF EXISTS pg_temp.' . $this->quoteIdentifier(self::DEFAULT_PROBE_TABLE),
                );
            }
        }

        return $probe !== null
            && $probe['column_default'] === $stored['column_default'];
    }

    /**
     * The probe table holding the expression whose stored form is compared, a temporary table that only the
     * current session sees.
     *
     * @throws ExpressionDefaultProbeException When PostgreSQL rejects the probe table
     */
    private function createDefaultProbe(
        string $table,
        string $column,
        string $columnType,
        Expression $expression,
    ): void {
        $sql = sprintf(
            'CREATE TEMP TABLE %s (%s %s DEFAULT %s)',
            $this->quoteIdentifier(self::DEFAULT_PROBE_TABLE),
            $this->quoteIdentifier(self::DEFAULT_PROBE_COLUMN),
            $columnType,
            $expression->sql,
        );

        try {
            $this->connection->execute($sql);
        } catch (QueryException $e) {
            throw ExpressionDefaultProbeException::rejected($table, $column, $expression->sql, $e->getMessage());
        }
    }

    /**
     * A column's type as PostgreSQL spells it (`character varying(255)`) and its default as
     * information_schema.columns reports it, with every cast; null when the relation has no such column.
     *
     * @param string $relation The table, schema-qualified and quoted (`"public"."events"`, `pg_temp.probe`)
     * @return array{column_type: string, column_default: string|null}|null
     */
    private function rawColumnDefault(
        string $relation,
        string $column,
    ): ?array {
        $sql = <<<'SQL'
            SELECT
                format_type(a.atttypid, a.atttypmod) AS column_type,
                pg_get_expr(d.adbin, d.adrelid) AS column_default
            FROM pg_attribute a
            LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
            WHERE a.attrelid = CAST(? AS regclass)
              AND a.attname = ?
              AND NOT a.attisdropped
            SQL;

        $row = $this->connection->query($sql, [$relation, $column])[0] ?? null;

        if ($row === null) {
            return null;
        }

        return [
            'column_type' => (string) $row['column_type'],
            'column_default' => $row['column_default'] === null ? null : (string) $row['column_default'],
        ];
    }

    private function quoteIdentifier(
        string $identifier,
    ): string {
        return PgSqlIdentifier::quote($identifier);
    }

    public function getIndexes(
        string $table,
    ): array {
        // Get primary key constraint index names so we can exclude them.
        // Primary keys are tracked as column flags, not as Index objects.
        $pkIndexNames = $this->getPrimaryKeyIndexNames($table);

        // is_constraint: the index backs a UNIQUE constraint (inline UNIQUE), which only DROP CONSTRAINT removes
        $sql = <<<'SQL'
            SELECT
                i.indexname,
                i.indexdef,
                EXISTS (
                    SELECT 1
                    FROM pg_constraint con
                    JOIN pg_class ic ON ic.oid = con.conindid
                    JOIN pg_namespace n ON n.oid = ic.relnamespace
                    WHERE con.contype = 'u'
                      AND ic.relname = i.indexname
                      AND n.nspname = i.schemaname
                ) AS is_constraint
            FROM pg_indexes i
            WHERE i.tablename = ?
              AND i.schemaname = ?
            ORDER BY i.indexname
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        $indexes = [];
        foreach ($rows as $row) {
            $name = $row['indexname'];

            // Skip primary key indexes — they are represented as column flags
            if (isset($pkIndexNames[$name])) {
                continue;
            }

            $indexDef = $row['indexdef'];

            $isUnique = str_contains($indexDef, 'UNIQUE INDEX');
            $columns = $this->parseIndexColumns($indexDef);

            $indexes[] = new Index(
                name: $name,
                columns: $columns,
                type: $isUnique ? IndexType::Unique : IndexType::Btree,
                where: $this->parseIndexPredicate($indexDef),
                constraint: in_array($row['is_constraint'] ?? false, [true, 't', 'true', '1', 1], true),
            );
        }

        return $indexes;
    }

    public function getForeignKeys(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT
                con.conname AS constraint_name,
                local_att.attname AS column_name,
                referenced_class.relname AS referenced_table,
                referenced_att.attname AS referenced_column,
                con.confdeltype AS delete_type,
                con.confupdtype AS update_type
            FROM pg_constraint con
            JOIN pg_class local_class ON local_class.oid = con.conrelid
            JOIN pg_namespace n ON n.oid = local_class.relnamespace
            JOIN pg_class referenced_class ON referenced_class.oid = con.confrelid
            CROSS JOIN LATERAL unnest(con.conkey, con.confkey) WITH ORDINALITY AS cols(local_num, referenced_num, ord)
            JOIN pg_attribute local_att
                ON local_att.attrelid = con.conrelid
                AND local_att.attnum = cols.local_num
            JOIN pg_attribute referenced_att
                ON referenced_att.attrelid = con.confrelid
                AND referenced_att.attnum = cols.referenced_num
            WHERE con.contype = 'f'
              AND local_class.relname = ?
              AND n.nspname = ?
            ORDER BY con.conname, cols.ord
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
                    'delete_rule' => self::FOREIGN_KEY_RULES[$row['delete_type']],
                    'update_rule' => self::FOREIGN_KEY_RULES[$row['update_type']],
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
     * Get the index names backing primary key constraints for a table.
     *
     * @return array<string, true>
     */
    private function getPrimaryKeyIndexNames(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT ic.relname AS index_name
            FROM pg_constraint con
            JOIN pg_class c ON c.oid = con.conrelid
            JOIN pg_class ic ON ic.oid = con.conindid
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE con.contype = 'p'
              AND c.relname = ?
              AND n.nspname = ?
            SQL;

        $rows = $this->connection->query($sql, [$table, $this->schema]);

        return array_fill_keys(array_column($rows, 'index_name'), true);
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

        return str_contains($default, 'nextval(')
            && str_contains($default, '_seq');
    }

    /**
     * The default the column declares: a string, bool, int or float for a literal, an Expression for anything
     * else (`gen_random_uuid()`, `CURRENT_TIMESTAMP`), and a Literal for a string that would otherwise read as
     * an expression shortcut, so a down migration restores it quoted.
     *
     * @throws MigrationException Only for an empty expression, which is returned as null before that
     */
    private function parseDefault(
        ?string $default,
        string $type,
    ): mixed {
        if (
            $default === null
            || trim($default) === ''
        ) {
            return null;
        }

        // Remove type cast suffix like ::character varying, ::integer, etc.
        $default = (string) preg_replace('/::[\w\s]+$/', '', $default);

        // An explicit DEFAULT NULL is no default
        if (strtoupper($default) === 'NULL') {
            return null;
        }

        // A string literal is the whole default between single quotes, with quotes inside it doubled
        if (preg_match("/^'((?:[^']|'')*)'$/", $default, $matches)) {
            $value = str_replace("''", "'", $matches[1]);

            return Expression::isShortcut($value) ? new Literal($value) : $value;
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

        // Everything else is an expression, such as CURRENT_TIMESTAMP or gen_random_uuid()
        return new Expression($default);
    }

    /**
     * Extract the predicate of a partial index from its definition, without the
     * outer parentheses PostgreSQL adds, or null for a full index.
     */
    private function parseIndexPredicate(
        string $indexDef,
    ): ?string {
        if (!preg_match('/\)\s+WHERE\s+(.+)$/is', $indexDef, $matches)) {
            return null;
        }

        $predicate = trim($matches[1]);

        return $this->isWrappedInOneParenthesisPair($predicate) ? substr($predicate, 1, -1) : $predicate;
    }

    /**
     * Whether the opening parenthesis at position 0 closes at the last character.
     */
    private function isWrappedInOneParenthesisPair(
        string $expression,
    ): bool {
        if (!str_starts_with($expression, '(')) {
            return false;
        }

        $depth = 0;
        $length = strlen($expression);

        for ($position = 0; $position < $length; $position++) {
            $character = $expression[$position];

            if ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0) {
                    return $position === $length - 1;
                }
            }
        }

        return false;
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
