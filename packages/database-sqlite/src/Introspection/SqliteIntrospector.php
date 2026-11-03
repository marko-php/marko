<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\DatabaseException;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;
use Marko\Database\Sqlite\Sql\SqliteGenerator;
use Marko\Database\Sqlite\Sql\SqliteIdentifier;

readonly class SqliteIntrospector implements IntrospectorInterface, ExpressionDefaultMatcherInterface
{
    private const string DEFAULT_PROBE_TABLE = '__marko_default_probe';

    private const string DEFAULT_PROBE_COLUMN = 'probe';

    /**
     * Tables SQLite owns (`sqlite_sequence`, ...) and the temporary tables a table rebuild creates.
     */
    private const string USER_TABLE_FILTER = <<<'SQL'
        type = 'table'
          AND name NOT LIKE 'sqlite\_%' ESCAPE '\'
          AND name NOT LIKE '\_\_marko\_rebuild\_%' ESCAPE '\'
          AND name != '__marko_fk_guard'
        SQL;

    /**
     * Declared base type to Marko type, the inverse of SqliteGenerator::TYPE_MAP.
     *
     * @var array<string, string>
     */
    private array $typeMap;

    public function __construct(
        private ConnectionInterface $connection,
    ) {
        $this->typeMap = [
            ...array_flip(array_reverse(SqliteGenerator::TYPE_MAP)),
            'INTEGER' => 'integer',
            'VARCHAR' => 'varchar',
            'BOOLEAN' => 'boolean',
            'BLOB' => 'blob',
        ];
    }

    /**
     * @return list<string>
     * @throws DatabaseException
     */
    public function getTables(): array
    {
        $rows = $this->connection->query(
            'SELECT name FROM sqlite_schema WHERE ' . self::USER_TABLE_FILTER . ' ORDER BY name',
        );

        return array_column($rows, 'name');
    }

    /**
     * @throws DatabaseException
     */
    public function getTable(
        string $name,
    ): ?Table {
        $columns = $this->getColumns($name);

        if ($columns === []) {
            return null;
        }

        return new Table(
            name: $name,
            columns: $columns,
            indexes: $this->getIndexes($name),
            foreignKeys: $this->getForeignKeys($name),
        );
    }

    /**
     * @throws DatabaseException
     */
    public function tableExists(
        string $name,
    ): bool {
        $rows = $this->connection->query(
            'SELECT 1 FROM sqlite_schema WHERE ' . self::USER_TABLE_FILTER . ' AND name = ?',
            [$name],
        );

        return $rows !== [];
    }

    /**
     * @return list<Column>
     * @throws DatabaseException
     */
    public function getColumns(
        string $table,
    ): array {
        $rows = $this->connection->query(
            'SELECT name, type, "notnull", dflt_value, pk FROM pragma_table_info(?) ORDER BY cid',
            [$table],
        );

        if ($rows === []) {
            return [];
        }

        $primaryKey = $this->getPrimaryKey($table);
        $createSql = $this->getCreateSql($table);
        $hasAutoIncrement = stripos($createSql, 'AUTOINCREMENT') !== false;

        $columns = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $declared = strtoupper(trim((string) $row['type']));
            $isPrimaryKey = in_array($name, $primaryKey, true);
            $autoIncrement = $hasAutoIncrement && $isPrimaryKey && count($primaryKey) === 1 && $declared === 'INTEGER';

            [$type, $length] = $autoIncrement
                ? [$this->readMarkoType($createSql, $name), null]
                : $this->parseDeclaredType($declared);

            $columns[] = new Column(
                name: $name,
                type: $type,
                length: $length,
                nullable: !$autoIncrement && (int) $row['notnull'] === 0,
                default: $this->parseDefault($row['dflt_value'], $type),
                primaryKey: $isPrimaryKey,
                autoIncrement: $autoIncrement,
            );
        }

        return $columns;
    }

    /**
     * Whether the column would report the default it has now if it were declared with $expression.
     *
     * Declares a temporary table with one column of the real column's declared type and the expression as its
     * default, reads back the default text SQLite stored for it, and compares that with the real column's.
     *
     * @throws DatabaseException|ExpressionDefaultProbeException
     */
    public function matchesStoredDefault(
        string $table,
        string $column,
        Expression $expression,
    ): bool {
        $stored = $this->connection->query(
            'SELECT type, dflt_value FROM pragma_table_info(?) WHERE name = ?',
            [$table, $column],
        )[0] ?? null;

        if ($stored === null || $stored['dflt_value'] === null) {
            return false;
        }

        $probe = SqliteIdentifier::quote(self::DEFAULT_PROBE_TABLE);

        try {
            $this->connection->execute(sprintf(
                'CREATE TEMP TABLE %s (%s %s DEFAULT (%s))',
                $probe,
                SqliteIdentifier::quote(self::DEFAULT_PROBE_COLUMN),
                $stored['type'],
                $expression->sql,
            ));
        } catch (QueryException $e) {
            throw ExpressionDefaultProbeException::rejected($table, $column, $expression->sql, $e->getMessage());
        }

        try {
            $probed = $this->connection->query(
                'SELECT dflt_value FROM pragma_table_info(?)',
                [self::DEFAULT_PROBE_TABLE],
            )[0]['dflt_value'] ?? null;
        } finally {
            $this->connection->execute('DROP TABLE IF EXISTS temp.' . $probe);
        }

        return $probed !== null && trim((string) $probed) === trim((string) $stored['dflt_value']);
    }

    /**
     * Named indexes only: the automatic indexes behind a PRIMARY KEY are the primary key, and an index on
     * an expression has no column to report.
     *
     * @return list<Index>
     * @throws DatabaseException
     */
    public function getIndexes(
        string $table,
    ): array {
        $rows = $this->connection->query(
            'SELECT name, "unique", origin FROM pragma_index_list(?) WHERE origin != \'pk\' ORDER BY name',
            [$table],
        );

        $indexes = [];
        foreach ($rows as $row) {
            $name = (string) $row['name'];
            $columns = $this->connection->query(
                'SELECT name FROM pragma_index_info(?) ORDER BY seqno',
                [$name],
            );
            $isUnique = (int) $row['unique'] === 1;

            $indexes[] = new Index(
                name: $name,
                columns: array_column($columns, 'name'),
                type: $isUnique ? IndexType::Unique : IndexType::Btree,
                where: $this->parseIndexPredicate($this->getIndexSql($name)),
                constraint: $row['origin'] === 'u',
            );
        }

        return $indexes;
    }

    /**
     * @return list<ForeignKey>
     * @throws DatabaseException
     */
    public function getForeignKeys(
        string $table,
    ): array {
        $rows = $this->connection->query(
            'SELECT id, seq, "table", "from", "to", on_update, on_delete FROM pragma_foreign_key_list(?) ORDER BY id, seq',
            [$table],
        );

        $grouped = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $grouped[$id] ??= [
                'columns' => [],
                'referenced_columns' => [],
                'table' => (string) $row['table'],
                'on_delete' => (string) $row['on_delete'],
                'on_update' => (string) $row['on_update'],
            ];
            $grouped[$id]['columns'][] = (string) $row['from'];
            $grouped[$id]['referenced_columns'][] = $row['to'] !== null
                ? (string) $row['to']
                : ($this->getPrimaryKey((string) $row['table'])[(int) $row['seq']] ?? '');
        }

        $createSql = $this->getCreateSql($table);
        $foreignKeys = [];
        foreach ($grouped as $data) {
            $foreignKeys[] = new ForeignKey(
                name: $this->foreignKeyName($table, $createSql, $data['columns']),
                columns: $data['columns'],
                referencedTable: $data['table'],
                referencedColumns: $data['referenced_columns'],
                onDelete: $data['on_delete'],
                onUpdate: $data['on_update'],
            );
        }

        return $foreignKeys;
    }

    /**
     * The name of a `CONSTRAINT "<name>" FOREIGN KEY (<columns>)` clause in the table's stored SQL, or the
     * name SchemaBuilder derives when the table was created without one.
     *
     * @param list<string> $columns
     */
    private function foreignKeyName(
        string $table,
        string $createSql,
        array $columns,
    ): string {
        $quoted = implode('\s*,\s*', array_map(
            static fn (string $column): string => preg_quote(SqliteIdentifier::quote($column), '/'),
            $columns,
        ));
        $pattern = '/CONSTRAINT\s+"((?:[^"]|"")+)"\s+FOREIGN\s+KEY\s*\(\s*' . $quoted . '\s*\)/i';

        if (preg_match($pattern, $createSql, $matches) === 1) {
            return str_replace('""', '"', $matches[1]);
        }

        return IdentifierName::derive($table . '_' . implode('_', $columns), prefix: 'fk_');
    }

    /**
     * @throws DatabaseException
     */
    private function getIndexSql(
        string $index,
    ): string {
        $rows = $this->connection->query(
            'SELECT sql FROM sqlite_schema WHERE type = \'index\' AND name = ?',
            [$index],
        );

        return (string) ($rows[0]['sql'] ?? '');
    }

    /**
     * The `WHERE` predicate of a stored CREATE INDEX statement, without one wrapping pair of parentheses.
     */
    private function parseIndexPredicate(
        string $indexSql,
    ): ?string {
        if (preg_match('/\)\s+WHERE\s+(.+)$/is', $indexSql, $matches) !== 1) {
            return null;
        }

        $predicate = trim($matches[1]);

        return $this->isWrappedInOneParenthesisPair($predicate) ? substr($predicate, 1, -1) : $predicate;
    }

    private function isWrappedInOneParenthesisPair(
        string $expression,
    ): bool {
        if (!str_starts_with($expression, '(')) {
            return false;
        }

        $depth = 0;
        $quote = null;
        $length = strlen($expression);

        for ($position = 0; $position < $length; $position++) {
            $character = $expression[$position];

            if ($quote !== null) {
                $quote = $character === $quote ? null : $quote;

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')' && --$depth === 0) {
                return $position === $length - 1;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     * @throws DatabaseException
     */
    public function getPrimaryKey(
        string $table,
    ): array {
        $rows = $this->connection->query(
            'SELECT name FROM pragma_table_info(?) WHERE pk > 0 ORDER BY pk',
            [$table],
        );

        return array_column($rows, 'name');
    }

    /**
     * @throws DatabaseException
     */
    private function getCreateSql(
        string $table,
    ): string {
        $rows = $this->connection->query(
            'SELECT sql FROM sqlite_schema WHERE type = \'table\' AND name = ?',
            [$table],
        );

        return (string) ($rows[0]['sql'] ?? '');
    }

    /**
     * The Marko type SqliteGenerator recorded as `/* marko:<type> *\/` after the INTEGER of an auto-increment key.
     */
    private function readMarkoType(
        string $createSql,
        string $column,
    ): string {
        $pattern = '/' . preg_quote(
            SqliteIdentifier::quote($column),
            '/',
        ) . '\s+INTEGER\s*\/\*\s*marko:(\w+)\s*\*\//i';

        return preg_match($pattern, $createSql, $matches) === 1 ? strtolower($matches[1]) : 'integer';
    }

    /**
     * @return array{string, int|null} The Marko type and its length
     */
    private function parseDeclaredType(
        string $declared,
    ): array {
        preg_match('/^([A-Z_ ]+?)\s*(?:\((\d+)(?:\s*,\s*\d+)?\))?$/', $declared, $matches);

        $base = $matches[1] ?? $declared;
        $type = $this->typeMap[$base] ?? strtolower($base);
        $length = $type === 'varchar' && isset($matches[2]) ? (int) $matches[2] : null;

        return [$type, $length];
    }

    private function parseDefault(
        mixed $default,
        string $type,
    ): mixed {
        if ($default === null) {
            return null;
        }

        $default = trim((string) $default);

        if (strtoupper($default) === 'NULL' || $default === '') {
            return null;
        }

        if (preg_match("/^'((?:[^']|'')*)'$/", $default, $matches) === 1) {
            $value = str_replace("''", "'", $matches[1]);

            return Expression::isShortcut($value) ? new Literal($value) : $value;
        }

        if (is_numeric($default)) {
            return match (true) {
                $type === 'boolean' => $default !== '0',
                in_array($type, ['integer', 'bigint', 'smallint', 'tinyint'], true) => (int) $default,
                default => (float) $default,
            };
        }

        return new Expression($default);
    }
}
