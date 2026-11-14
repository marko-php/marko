<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Introspection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\ExpressionDefaultProbeException;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\Introspection\ExpressionDefaultMatcherInterface;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\MySql\Connection\MySqlServer;
use Marko\Database\MySql\Exceptions\ServerVersionException;
use Marko\Database\MySql\Sql\MySqlIdentifier;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;

readonly class MySqlIntrospector implements IntrospectorInterface, ExpressionDefaultMatcherInterface
{
    /**
     * The temporary table and column matchesStoredDefault() declares an expression default on.
     */
    private const string DEFAULT_PROBE_TABLE = 'marko_default_probe';

    private const string DEFAULT_PROBE_COLUMN = 'probe';

    /**
     * Expression defaults MySqlGenerator writes without parentheses: CURRENT_TIMESTAMP and its synonyms, with or
     * without a precision.
     */
    private const string BARE_EXPRESSION_PATTERN =
        '/^(?:(?:CURRENT_TIMESTAMP|LOCALTIMESTAMP|LOCALTIME)(?:\(\d*\))?|NOW\(\d*\))$/i';

    /**
     * The CURRENT_TIMESTAMP family, the only expression defaults a server without DEFAULT_GENERATED reports.
     */
    private const string TIMESTAMP_KEYWORD_PATTERN =
        '/^(?:CURRENT_TIMESTAMP|LOCALTIMESTAMP|LOCALTIME)(?:\(\d*\))?$/i';

    /**
     * MySQL data types mapped to the abstract names entities declare (the inverse of MySqlGenerator's type map), so
     * both sides of the schema diff use one vocabulary. Unlisted types keep the name MySQL reports, lowercased.
     *
     * @var array<string, string>
     */
    private const array TYPE_MAP = [
        'int' => 'integer',
        'integer' => 'integer',
        'bigint' => 'bigint',
        'smallint' => 'smallint',
        'tinyint' => 'tinyint',
        'varchar' => 'varchar',
        'char' => 'char',
        'text' => 'text',
        'datetime' => 'datetime',
        'date' => 'date',
        'time' => 'time',
        'timestamp' => 'timestamp',
        'decimal' => 'decimal',
        'float' => 'float',
        'double' => 'double',
        'blob' => 'blob',
        'json' => 'json',
    ];

    /**
     * Full column types that name an abstract type on their own: MySqlGenerator renders `boolean` as TINYINT(1) and
     * `uuid` as CHAR(36), and MySQL has no other way to store either.
     *
     * @var array<string, string>
     */
    private const array COLUMN_TYPE_MAP = [
        'tinyint(1)' => 'boolean',
        'char(36)' => 'uuid',
    ];

    private const array INTEGER_TYPES = ['integer', 'bigint', 'smallint', 'tinyint'];

    private const array FLOAT_TYPES = ['decimal', 'float', 'double'];

    private MySqlServer $server;

    /**
     * @param MySqlServer|null $server The shared server check; built from $connection when omitted
     */
    public function __construct(
        private ConnectionInterface $connection,
        private string $database,
        ?MySqlServer $server = null,
    ) {
        $this->server = $server ?? new MySqlServer($connection);
    }

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
        // Column::$unique is informational; the index diff matches unique columns with their unique indexes
        $uniqueColumns = $this->getUniqueColumns($name);
        $columns = $this->getColumns($name, $primaryKeyColumns, $uniqueColumns);
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
        return in_array($name, $this->getTables(), true);
    }

    /**
     * @param array<string> $primaryKeyColumns
     * @param array<string> $uniqueColumns
     * @return array<Column>
     * @throws ServerVersionException When the server reports no readable version
     */
    public function getColumns(
        string $table,
        array $primaryKeyColumns = [],
        array $uniqueColumns = [],
    ): array {
        // A collation is reported only where it differs from the table's default, so restating a column
        // pins nothing the table would not give it anyway
        $sql = <<<'SQL'
            SELECT
                c.COLUMN_NAME,
                c.DATA_TYPE,
                c.CHARACTER_MAXIMUM_LENGTH,
                c.IS_NULLABLE,
                c.COLUMN_DEFAULT,
                c.EXTRA,
                c.COLUMN_TYPE,
                CASE WHEN c.COLLATION_NAME = t.TABLE_COLLATION THEN NULL ELSE c.COLLATION_NAME END AS COLLATION_NAME
            FROM information_schema.columns c
            JOIN information_schema.tables t
                ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
                AND t.TABLE_NAME = c.TABLE_NAME
            WHERE c.TABLE_SCHEMA = ?
            AND c.TABLE_NAME = ?
            ORDER BY c.ORDINAL_POSITION
        SQL;

        $rows = $this->connection->query($sql, [$this->database, $table]);
        $mariaDb = $this->server->isMariaDb();
        $hasLongtext = in_array('longtext', array_map(strtolower(...), array_column($rows, 'DATA_TYPE')), true);
        $jsonColumns = ($mariaDb && $hasLongtext)
            ? $this->getMariaDbJsonColumns($table)
            : [];
        $columns = [];

        foreach ($rows as $row) {
            $columnName = $row['COLUMN_NAME'];
            $length = $row['CHARACTER_MAXIMUM_LENGTH'] !== null
                ? (int) $row['CHARACTER_MAXIMUM_LENGTH']
                : null;

            $isPrimaryKey = in_array($columnName, $primaryKeyColumns, true);
            $isUnique = in_array($columnName, $uniqueColumns, true);

            if (
                strtolower($row['DATA_TYPE']) === 'longtext'
                && in_array($columnName, $jsonColumns, true)
            ) {
                // MariaDB's JSON is an alias for LONGTEXT with a json_valid() check and utf8mb4_bin
                $row = [...$row, 'DATA_TYPE' => 'json', 'COLUMN_TYPE' => 'json', 'COLLATION_NAME' => null];
                $length = null;
            }

            $type = $this->mapType($row['DATA_TYPE'], $row['COLUMN_TYPE']);
            $default = $mariaDb
                ? $this->parseMariaDbDefault($row['COLUMN_DEFAULT'])
                : $this->parseDefault($row['COLUMN_DEFAULT'], $row['EXTRA']);

            $columns[] = new Column(
                name: $columnName,
                type: $type,
                length: $length,
                nullable: $row['IS_NULLABLE'] === 'YES',
                default: $this->castDefault($default, $type),
                unique: $isUnique,
                primaryKey: $isPrimaryKey,
                autoIncrement: str_contains($row['EXTRA'], 'auto_increment'),
                nativeType: $row['COLUMN_TYPE'],
                collation: $row['COLLATION_NAME'],
                onUpdateExpression: $this->onUpdateExpression($row['EXTRA']),
            );
        }

        return $columns;
    }

    /**
     * Whether the column would report the default it has now if it were declared with $expression.
     *
     * It creates a temporary table with one column of the real column's type and the expression as its default
     * (CREATE and DROP TEMPORARY TABLE never commit a transaction), reads the stored default back with SHOW
     * COLUMNS, since temporary tables are not in information_schema, and drops the table again. SHOW COLUMNS
     * spells a temporary table's default without the backslash escaping MySQL's information_schema applies
     * (`concat(_utf8mb4'a')` against `concat(_utf8mb4\'a\')`) and in one more pair of parentheses, so the real
     * column's default is unescaped (MySQL only; MariaDB does not escape it) and both sides are compared without
     * wrapping parentheses.
     *
     * @throws ExpressionDefaultProbeException|ServerVersionException
     */
    public function matchesStoredDefault(
        string $table,
        string $column,
        Expression $expression,
    ): bool {
        $sql = <<<'SQL'
            SELECT COLUMN_TYPE, COLUMN_DEFAULT
            FROM information_schema.columns
            WHERE TABLE_SCHEMA = ?
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        SQL;

        $row = $this->connection->query($sql, [$this->database, $table, $column])[0] ?? null;

        if (
            $row === null
            || $row['COLUMN_DEFAULT'] === null
        ) {
            return false;
        }

        $stored = (string) $row['COLUMN_DEFAULT'];
        $stored = $this->server->isMariaDb() ? $stored : strtr($stored, ['\\\\' => '\\', "\\'" => "'"]);
        $probe = $this->probeDefault($table, $column, (string) $row['COLUMN_TYPE'], $expression);

        return $probe !== null
            && Expression::unwrap($probe) === Expression::unwrap($stored);
    }

    /**
     * The default the server stores for $expression on a column of $columnType, as SHOW COLUMNS reports it.
     *
     * @throws ExpressionDefaultProbeException When the server rejects the probe table
     */
    private function probeDefault(
        string $table,
        string $column,
        string $columnType,
        Expression $expression,
    ): ?string {
        $probeTable = MySqlIdentifier::quote(self::DEFAULT_PROBE_TABLE);
        $dropProbe = "DROP TEMPORARY TABLE IF EXISTS $probeTable";
        $createProbe = sprintf(
            'CREATE TEMPORARY TABLE %s (%s %s NULL DEFAULT %s)',
            $probeTable,
            MySqlIdentifier::quote(self::DEFAULT_PROBE_COLUMN),
            $columnType,
            $this->formatDefaultExpression($expression->sql),
        );

        $this->connection->execute($dropProbe);

        try {
            try {
                $this->connection->execute($createProbe);
            } catch (QueryException $e) {
                throw ExpressionDefaultProbeException::rejected(
                    $table,
                    $column,
                    $expression->sql,
                    $e->getMessage(),
                );
            }

            $rows = $this->connection->query("SHOW COLUMNS FROM $probeTable");
        } finally {
            $this->connection->execute($dropProbe);
        }

        $default = $rows[0]['Default'] ?? null;

        return $default === null ? null : (string) $default;
    }

    /**
     * An expression default as MySqlGenerator writes it: the CURRENT_TIMESTAMP family bare, anything else in
     * parentheses (MySQL 8.0.13+ requires them).
     */
    private function formatDefaultExpression(
        string $sql,
    ): string {
        $sql = trim($sql);

        if (preg_match(self::BARE_EXPRESSION_PATTERN, $sql) === 1) {
            return $sql;
        }

        return '(' . Expression::unwrap($sql) . ')';
    }

    /**
     * The default the column declares. MySQL 8.0.13+ marks an expression default with DEFAULT_GENERATED in
     * EXTRA; it is returned as an Expression. A literal that would otherwise read as an expression shortcut
     * (`now()` stored as text) is returned as a Literal, so a down migration restores it quoted. Servers that
     * never report DEFAULT_GENERATED (MySQL before 8.0.13, MariaDB) can only have the CURRENT_TIMESTAMP family
     * as an expression, so that stays a plain string, which reads as the expression it is.
     *
     * @throws MigrationException Only for an empty expression, which MySQL never reports as generated
     */
    private function parseDefault(
        ?string $default,
        string $extra,
    ): mixed {
        if ($default === null) {
            return null;
        }

        if (
            str_contains(strtoupper($extra), 'DEFAULT_GENERATED')
            && trim($default) !== ''
        ) {
            return new Expression($default);
        }

        if (
            Expression::isShortcut($default)
            && preg_match(self::TIMESTAMP_KEYWORD_PATTERN, $default) !== 1
        ) {
            return new Literal($default);
        }

        return $default;
    }

    /**
     * The default as MariaDB (10.2.7+) reports it: a string literal quoted (`'abc'`, with quotes inside doubled), no
     * default or DEFAULT NULL as the string `NULL`, and a number or an expression unquoted. `current_timestamp()`,
     * MariaDB's spelling of CURRENT_TIMESTAMP, comes back as CURRENT_TIMESTAMP so it reads as MySQL reports it.
     *
     * @throws MigrationException Only for an empty expression, which MariaDB reports quoted
     */
    private function parseMariaDbDefault(
        ?string $default,
    ): mixed {
        if (
            $default === null
            || strtoupper($default) === 'NULL'
        ) {
            return null;
        }

        if (preg_match("/^'((?:[^']|'')*)'$/s", $default, $matches) === 1) {
            $value = str_replace("''", "'", $matches[1]);

            return Expression::isShortcut($value) ? new Literal($value) : $value;
        }

        if (is_numeric($default)) {
            return $default;
        }

        if (preg_match('/^current_timestamp\((\d*)\)$/i', $default, $matches) === 1) {
            return $matches[1] === '' ? 'CURRENT_TIMESTAMP' : "CURRENT_TIMESTAMP({$matches[1]})";
        }

        return new Expression($default);
    }

    /**
     * A literal default as the PHP value an entity declares for the column's type: an integer for the integer types,
     * a bool for `boolean`, a float for the decimal types. Anything else (a string column's default, an expression,
     * a value that does not fit the type) is returned unchanged.
     */
    private function castDefault(
        mixed $default,
        string $type,
    ): mixed {
        if (!is_string($default)) {
            return $default;
        }

        if (
            $type === 'boolean'
            && (
                $default === '0'
                || $default === '1'
            )
        ) {
            return $default === '1';
        }

        if (
            in_array($type, self::INTEGER_TYPES, true)
            && preg_match('/^-?\d+$/', $default) === 1
        ) {
            return (int) $default;
        }

        if (
            in_array($type, self::FLOAT_TYPES, true)
            && is_numeric($default)
        ) {
            return (float) $default;
        }

        return $default;
    }

    /**
     * The abstract type of a column from its DATA_TYPE and full COLUMN_TYPE (`tinyint(1)` is `boolean`).
     */
    private function mapType(
        string $dataType,
        string $columnType,
    ): string {
        $columnType = strtolower($columnType);
        $dataType = strtolower($dataType);

        return self::COLUMN_TYPE_MAP[$columnType] ?? self::TYPE_MAP[$dataType] ?? $dataType;
    }

    /**
     * The columns of $table that MariaDB created as JSON. MariaDB stores JSON as LONGTEXT and adds a column check
     * `json_valid(`col`)`, which is the only trace of the declared type.
     *
     * @return list<string>
     */
    private function getMariaDbJsonColumns(
        string $table,
    ): array {
        $sql = <<<'SQL'
            SELECT CONSTRAINT_NAME, CHECK_CLAUSE
            FROM information_schema.CHECK_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
            AND TABLE_NAME = ?
        SQL;

        $columns = [];

        foreach ($this->connection->query($sql, [$this->database, $table]) as $row) {
            if (
                preg_match('/^json_valid\(`((?:[^`]|``)+)`\)$/i', trim((string) $row['CHECK_CLAUSE']), $matches) === 1
                && $matches[1] === $row['CONSTRAINT_NAME']
            ) {
                $columns[] = str_replace('``', '`', $matches[1]);
            }
        }

        return $columns;
    }

    /**
     * The ON UPDATE expression in a column's EXTRA, such as `CURRENT_TIMESTAMP(3)` from
     * `DEFAULT_GENERATED on update CURRENT_TIMESTAMP(3)`. MariaDB's `on update current_timestamp()` comes back as
     * CURRENT_TIMESTAMP, as MySQL reports it.
     */
    private function onUpdateExpression(
        string $extra,
    ): ?string {
        if (preg_match('/\bon update (\S+)/i', $extra, $matches) !== 1) {
            return null;
        }

        if (preg_match('/^current_timestamp\((\d*)\)$/i', $matches[1], $precision) === 1) {
            return $precision[1] === '' ? 'CURRENT_TIMESTAMP' : "CURRENT_TIMESTAMP({$precision[1]})";
        }

        return $matches[1];
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
     * Every index except the primary key, single-column unique indexes (inline UNIQUE) included.
     *
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
