<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Sql;

use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;

/**
 * SQLite-specific SQL generator for schema migrations.
 *
 * Tables are not STRICT: STRICT would erase the declared Marko-style types, which the introspector reads back
 * from pragma_table_info to avoid spurious diffs. SQLite's type affinity still governs storage.
 */
class SqliteGenerator implements SqlGeneratorInterface
{
    private const string DRIVER = 'SQLite';

    /**
     * Abstract column types to declared SQLite types. The introspector inverts this map.
     *
     * @var array<string, string>
     */
    public const array TYPE_MAP = [
        'integer' => 'INTEGER',
        'int' => 'INTEGER',
        'bigint' => 'BIGINT',
        'smallint' => 'SMALLINT',
        'tinyint' => 'TINYINT',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'boolean' => 'BOOLEAN',
        'bool' => 'BOOLEAN',
        'datetime' => 'DATETIME',
        'timestamp' => 'TIMESTAMP',
        'date' => 'DATE',
        'time' => 'TIME',
        'decimal' => 'DECIMAL(10,2)',
        'float' => 'REAL',
        'double' => 'DOUBLE',
        'json' => 'JSON',
        'uuid' => 'UUID',
        'binary' => 'BLOB',
        'blob' => 'BLOB',
        'enum' => 'VARCHAR',
    ];

    /**
     * The only expression defaults SQLite accepts without parentheses. They are always evaluated in UTC.
     */
    private const array BARE_DEFAULTS = ['current_timestamp', 'current_date', 'current_time'];

    private const string UTC = 'UTC';

    /** Temporary names the introspector ignores */
    private const string REBUILD_PREFIX = '__marko_rebuild_';

    private const string FK_GUARD = '__marko_fk_guard';

    public function __construct(
        private readonly DatabaseTimezoneConfig $databaseTimezoneConfig,
    ) {}

    /**
     * @return list<string>
     * @throws MigrationException
     */
    public function generateUp(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        foreach ($diff->tablesToCreate as $table) {
            $statements = [...$statements, ...$this->createTableStatements($table)];
        }

        foreach ($diff->tablesToDrop as $table) {
            $statements[] = $this->generateDropTable($table->name);
        }

        foreach ($diff->tablesToAlter as $tableDiff) {
            $statements = [...$statements, ...$this->alterStatements($tableDiff, reverse: false)];
        }

        return $statements;
    }

    /**
     * @return list<string>
     * @throws MigrationException
     */
    public function generateDown(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        foreach ($diff->tablesToCreate as $table) {
            $statements[] = $this->generateDropTable($table->name);
        }

        foreach ($diff->tablesToDrop as $table) {
            $statements = [...$statements, ...$this->createTableStatements($table)];
        }

        foreach ($diff->tablesToAlter as $tableDiff) {
            $statements = [...$statements, ...$this->alterStatements($tableDiff, reverse: true)];
        }

        return $statements;
    }

    /**
     * @throws MigrationException
     */
    public function generateCreateTable(
        Table $table,
        ?string $defaultsTable = null,
    ): string {
        $keyColumns = array_values(array_filter(
            $table->columns,
            static fn (Column $column): bool => $column->primaryKey,
        ));
        $composite = count($keyColumns) > 1;
        $definitions = array_map(
            fn (Column $column): string => $this->generateColumnDefinition(
                $defaultsTable ?? $table->name,
                $column,
                inlinePrimaryKey: !$composite,
            ),
            $table->columns,
        );

        if ($composite) {
            $definitions[] = 'PRIMARY KEY (' . implode(', ', array_map(
                static fn (Column $column): string => SqliteIdentifier::quote($column->name),
                $keyColumns,
            )) . ')';
        }

        foreach ($table->foreignKeys as $foreignKey) {
            $definitions[] = $this->generateForeignKeyConstraint($foreignKey);
        }

        return 'CREATE TABLE ' . SqliteIdentifier::quote($table->name)
            . " (\n    " . implode(",\n    ", $definitions) . "\n)";
    }

    public function generateDropTable(
        string $tableName,
    ): string {
        return 'DROP TABLE ' . SqliteIdentifier::quote($tableName);
    }

    /**
     * @throws MigrationException
     */
    public function generateAddColumn(
        string $table,
        Column $column,
    ): string {
        return 'ALTER TABLE ' . SqliteIdentifier::quote($table)
            . ' ADD COLUMN ' . $this->generateColumnDefinition($table, $column);
    }

    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string {
        return 'ALTER TABLE ' . SqliteIdentifier::quote($table) . ' DROP COLUMN ' . SqliteIdentifier::quote(
            $columnName,
        );
    }

    /**
     * @throws MigrationException
     */
    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string {
        throw MigrationException::columnChangeNotSupported($table, $column->name, self::DRIVER, 'definition');
    }

    /**
     * @throws MigrationException When the index is a fulltext index
     */
    public function generateAddIndex(
        string $table,
        Index $index,
    ): string {
        if ($index->type === IndexType::Fulltext) {
            throw MigrationException::unsupportedIndexType($index->name, 'fulltext', self::DRIVER);
        }

        $unique = $index->type === IndexType::Unique || $index->constraint ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(SqliteIdentifier::quote(...), $index->columns));
        $where = $index->where !== null ? " WHERE $index->where" : '';

        return "CREATE {$unique}INDEX " . SqliteIdentifier::quote($index->name)
            . ' ON ' . SqliteIdentifier::quote($table) . " ($columns)$where";
    }

    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string {
        // SQLite index names are schema-global, so DROP INDEX has no table clause
        return 'DROP INDEX ' . SqliteIdentifier::quote($indexName);
    }

    /**
     * @throws MigrationException Always: SQLite adds foreign keys only by rebuilding the table
     */
    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string {
        throw MigrationException::foreignKeyChangeRequiresRebuild($table, $foreignKey->name, self::DRIVER);
    }

    /**
     * @throws MigrationException Always: SQLite drops foreign keys only by rebuilding the table
     */
    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string {
        throw MigrationException::foreignKeyChangeRequiresRebuild($table, $keyName, self::DRIVER);
    }

    /**
     * Whether a table diff has to run as a table rebuild in the given direction: SQLite alters in place only
     * what ADD COLUMN, DROP COLUMN, CREATE INDEX and DROP INDEX can express.
     */
    protected function requiresRebuild(
        TableDiff $diff,
        bool $reverse = false,
    ): bool {
        if ($diff->columnsToModify !== [] || $diff->foreignKeysToAdd !== [] || $diff->foreignKeysToDrop !== []) {
            return true;
        }

        $added = $reverse ? $diff->columnsToDrop : $diff->columnsToAdd;
        $dropped = $reverse ? $diff->columnsToAdd : $diff->columnsToDrop;

        return array_any($added, fn (Column $column): bool => !$this->canAddInPlace($column))
            || array_any($dropped, fn (Column $column): bool => !$this->canDropInPlace($diff, $column, $reverse));
    }

    /**
     * Rebuild the whole table, SQLite's documented way to change what ALTER TABLE cannot: create the new shape
     * under a temporary name, copy the rows, swap the tables and recreate every index. Going up it rebuilds
     * the table from its current definition to the diffed one; going down it rebuilds back.
     *
     * Foreign keys are switched off for the swap (the Migrator runs migrations outside a transaction, so the
     * pragma takes effect) and a guard statement errors before COMMIT when any foreign key is violated. If a
     * statement fails mid-way the transaction stays open: ROLLBACK restores the original table and rows.
     *
     * @return list<string>
     * @throws MigrationException
     */
    protected function generateRebuild(
        TableDiff $diff,
        bool $reverse,
    ): array {
        $current = $diff->currentTable
            ?? throw MigrationException::rebuildNeedsCurrentTable($diff->tableName, self::DRIVER);
        $changed = $this->applyDiff($current, $diff);
        [$from, $to] = $reverse ? [$changed, $current] : [$current, $changed];

        $table = $to->name;
        $temporary = self::REBUILD_PREFIX . $table;
        $shared = array_values(array_intersect(
            array_map(static fn (Column $column): string => $column->name, $to->columns),
            array_map(static fn (Column $column): string => $column->name, $from->columns),
        ));
        $columns = implode(', ', array_map(SqliteIdentifier::quote(...), $shared));
        $guard = SqliteIdentifier::quote(self::FK_GUARD);

        return [
            'PRAGMA foreign_keys = OFF',
            'BEGIN IMMEDIATE',
            $this->generateCreateTable(new Table($temporary, $to->columns, $to->indexes, $to->foreignKeys), $table),
            'INSERT INTO ' . SqliteIdentifier::quote($temporary) . " ($columns) SELECT $columns FROM "
                . SqliteIdentifier::quote($table),
            $this->generateDropTable($table),
            'ALTER TABLE ' . SqliteIdentifier::quote($temporary) . ' RENAME TO ' . SqliteIdentifier::quote($table),
            ...$this->indexStatements($to),
            "CREATE TEMP TABLE $guard (\"violations\" INTEGER NOT NULL CHECK (\"violations\" = 0))",
            "INSERT INTO $guard SELECT COUNT(*) FROM pragma_foreign_key_check",
            "DROP TABLE $guard",
            'COMMIT',
            'PRAGMA foreign_keys = ON',
        ];
    }

    /**
     * The table as it looks once the diff is applied to its current definition.
     */
    private function applyDiff(
        Table $current,
        TableDiff $diff,
    ): Table {
        $dropped = array_map(static fn (Column $column): string => $column->name, $diff->columnsToDrop);
        $columns = [];

        foreach ($current->columns as $column) {
            if (!in_array($column->name, $dropped, true)) {
                $columns[] = $diff->columnsToModify[$column->name] ?? $column;
            }
        }

        $droppedIndexes = array_map(static fn (Index $index): string => $index->name, $diff->indexesToDrop);
        $droppedKeys = array_map(static fn (ForeignKey $key): string => $key->name, $diff->foreignKeysToDrop);

        return new Table(
            name: $current->name,
            columns: [...$columns, ...$diff->columnsToAdd],
            indexes: [
                ...array_filter(
                    $current->indexes,
                    static fn (Index $i): bool => !in_array($i->name, $droppedIndexes, true),
                ),
                ...$diff->indexesToAdd,
            ],
            foreignKeys: [
                ...array_filter(
                    $current->foreignKeys,
                    static fn (ForeignKey $k): bool => !in_array($k->name, $droppedKeys, true),
                ),
                ...$diff->foreignKeysToAdd,
            ],
            unmanagedIndexes: $current->unmanagedIndexes,
        );
    }

    /**
     * CREATE INDEX for every index of the table, plus the named unique index of each unique column that the
     * index list does not already carry.
     *
     * @return list<string>
     * @throws MigrationException
     */
    private function indexStatements(
        Table $table,
    ): array {
        $indexes = $table->indexes;
        $names = array_map(static fn (Index $index): string => $index->name, $indexes);

        foreach ($table->columns as $column) {
            if ($this->needsUniqueIndex($column) && !in_array(
                $this->uniqueIndex($table->name, $column)->name,
                $names,
                true,
            )) {
                $indexes[] = $this->uniqueIndex($table->name, $column);
            }
        }

        return array_map(fn (Index $index): string => $this->generateAddIndex($table->name, $index), $indexes);
    }

    /**
     * @return list<string>
     * @throws MigrationException
     */
    private function alterStatements(
        TableDiff $diff,
        bool $reverse,
    ): array {
        $diff->assertSupportedPrimaryKeyChange(self::DRIVER);

        if ($this->requiresRebuild($diff, $reverse)) {
            return $this->generateRebuild($diff, $reverse);
        }

        return $reverse ? $this->reverseNativeStatements($diff) : $this->nativeStatements($diff);
    }

    /**
     * @return list<string>
     * @throws MigrationException
     */
    private function nativeStatements(
        TableDiff $diff,
    ): array {
        $table = $diff->tableName;
        $statements = [];

        foreach ($diff->indexesToDrop as $index) {
            $statements[] = $this->generateDropIndex($table, $index->name);
        }

        foreach ($diff->columnsToDrop as $column) {
            $statements[] = $this->generateDropColumn($table, $column->name);
        }

        $statements = [...$statements, ...$this->addColumnStatements($table, $diff->columnsToAdd)];

        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateAddIndex($table, $index);
        }

        return $statements;
    }

    /**
     * @return list<string>
     * @throws MigrationException
     */
    private function reverseNativeStatements(
        TableDiff $diff,
    ): array {
        $table = $diff->tableName;
        $statements = [];

        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateDropIndex($table, $index->name);
        }

        foreach ($diff->columnsToAdd as $column) {
            if ($this->needsUniqueIndex($column)) {
                $statements[] = $this->generateDropIndex($table, $this->uniqueIndex($table, $column)->name);
            }

            $statements[] = $this->generateDropColumn($table, $column->name);
        }

        $statements = [...$statements, ...$this->addColumnStatements($table, $diff->columnsToDrop)];
        $restored = array_map(
            fn (Column $column): string => $this->uniqueIndex($table, $column)->name,
            array_filter($diff->columnsToDrop, $this->needsUniqueIndex(...)),
        );

        foreach ($diff->indexesToDrop as $index) {
            if (!in_array($index->name, $restored, true)) {
                $statements[] = $this->generateAddIndex($table, $index);
            }
        }

        return $statements;
    }

    /**
     * ADD COLUMN per column; a unique column gets its named unique index right after, because SQLite rejects
     * UNIQUE in ADD COLUMN.
     *
     * @param array<Column> $columns
     * @return list<string>
     * @throws MigrationException
     */
    private function addColumnStatements(
        string $table,
        array $columns,
    ): array {
        $statements = [];

        foreach ($columns as $column) {
            $statements[] = $this->generateAddColumn($table, $column);

            if ($this->needsUniqueIndex($column)) {
                $statements[] = $this->generateAddIndex($table, $this->uniqueIndex($table, $column));
            }
        }

        return $statements;
    }

    private function needsUniqueIndex(
        Column $column,
    ): bool {
        return $column->unique && !$column->primaryKey;
    }

    private function uniqueIndex(
        string $table,
        Column $column,
    ): Index {
        return new Index(
            name: IdentifierName::derive("{$table}_$column->name", suffix: '_unique'),
            columns: [$column->name],
            type: IndexType::Unique,
        );
    }

    /**
     * ADD COLUMN cannot add a primary key, a NOT NULL column without a constant default, an expression
     * default or a REFERENCES clause.
     */
    private function canAddInPlace(
        Column $column,
    ): bool {
        $default = $column->default;
        $isExpression = $default instanceof Expression || (is_string($default) && Expression::isShortcut($default));

        return !$column->primaryKey
            && $column->references === null
            && !$isExpression
            && ($column->nullable || $default !== null);
    }

    /**
     * DROP COLUMN cannot drop a primary key column or a column that an index, foreign key or partial index
     * predicate still uses; an index the diff drops itself does not count.
     */
    private function canDropInPlace(
        TableDiff $diff,
        Column $column,
        bool $reverse,
    ): bool {
        if ($column->primaryKey || in_array($column->name, $diff->currentPrimaryKey, true)) {
            return false;
        }

        $current = $diff->currentTable;

        if ($reverse || $current === null) {
            return true;
        }

        $droppedIndexes = array_map(static fn (Index $index): string => $index->name, $diff->indexesToDrop);
        $predicate = '/(?<![A-Za-z0-9_])' . preg_quote($column->name, '/') . '(?![A-Za-z0-9_])/i';

        $indexed = array_any(
            $current->indexes,
            static fn (Index $index): bool => !in_array($index->name, $droppedIndexes, true)
                && (in_array($column->name, $index->columns, true)
                    || ($index->where !== null && preg_match($predicate, $index->where) === 1)),
        );

        return !$indexed && !array_any(
            $current->foreignKeys,
            static fn (ForeignKey $foreignKey): bool => in_array($column->name, $foreignKey->columns, true),
        );
    }

    /**
     * The CREATE TABLE, then a named unique index per unique column (an inline UNIQUE would create an
     * unnamed autoindex that DROP INDEX refuses), then the table's own indexes.
     *
     * @return list<string>
     * @throws MigrationException
     */
    private function createTableStatements(
        Table $table,
    ): array {
        $statements = [$this->generateCreateTable($table)];

        foreach ($table->columns as $column) {
            if ($column->unique && !$column->primaryKey) {
                $statements[] = $this->generateAddIndex($table->name, new Index(
                    name: IdentifierName::derive("{$table->name}_$column->name", suffix: '_unique'),
                    columns: [$column->name],
                    type: IndexType::Unique,
                ));
            }
        }

        foreach ($table->indexes as $index) {
            $statements[] = $this->generateAddIndex($table->name, $index);
        }

        return $statements;
    }

    /**
     * @throws MigrationException
     */
    private function generateColumnDefinition(
        string $table,
        Column $column,
        bool $inlinePrimaryKey = true,
    ): string {
        $parts = [SqliteIdentifier::quote($column->name)];

        if ($inlinePrimaryKey && $column->primaryKey && $column->autoIncrement) {
            // The rowid alias must be declared exactly INTEGER; the Marko type is kept in a comment
            return implode(' ', [...$parts, 'INTEGER', "/* marko:$column->type */", 'PRIMARY KEY AUTOINCREMENT']);
        }

        $parts[] = $this->mapType($column);

        if (!$column->nullable) {
            $parts[] = 'NOT NULL';
        }

        if ($column->default !== null) {
            $parts[] = 'DEFAULT ' . $this->formatDefaultValue($table, $column);
        }

        if ($inlinePrimaryKey && $column->primaryKey) {
            $parts[] = 'PRIMARY KEY';
        }

        return implode(' ', $parts);
    }

    private function mapType(
        Column $column,
    ): string {
        $baseType = self::TYPE_MAP[$column->type] ?? strtoupper($column->type);

        if ($baseType === 'VARCHAR') {
            return 'VARCHAR(' . ($column->length ?? 255) . ')';
        }

        return $baseType;
    }

    /**
     * @throws MigrationException
     */
    private function formatDefaultValue(
        string $table,
        Column $column,
    ): string {
        $value = $column->default;

        return match (true) {
            $value instanceof Expression => $this->formatExpressionDefault($table, $column, $value->sql),
            $value instanceof Literal => $this->quoteString($value->value),
            is_bool($value) => $value ? '1' : '0',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => Expression::isShortcut($value)
                ? $this->formatExpressionDefault($table, $column, $value)
                : $this->quoteString($value),
            default => 'NULL',
        };
    }

    /**
     * @throws MigrationException
     */
    private function formatExpressionDefault(
        string $table,
        Column $column,
        string $sql,
    ): string {
        if (in_array(strtolower(trim($sql)), self::BARE_DEFAULTS, true)) {
            $this->assertUtcTimezone($table, $column, $sql);

            return strtoupper(trim($sql));
        }

        if (Expression::isShortcut($sql)) {
            throw MigrationException::unsupportedDefaultExpression($table, $column->name, $sql, self::DRIVER);
        }

        return "($sql)";
    }

    /**
     * @throws MigrationException
     */
    private function assertUtcTimezone(
        string $table,
        Column $column,
        string $expression,
    ): void {
        $timezone = $this->databaseTimezoneConfig->timezone->getName();

        if ($timezone !== self::UTC && $timezone !== '+00:00') {
            throw MigrationException::nonUtcCurrentTimeDefault(
                $table,
                $column->name,
                $expression,
                $timezone,
                self::DRIVER,
            );
        }
    }

    private function generateForeignKeyConstraint(
        ForeignKey $foreignKey,
    ): string {
        $sql = 'CONSTRAINT ' . SqliteIdentifier::quote($foreignKey->name)
            . ' FOREIGN KEY (' . implode(', ', array_map(SqliteIdentifier::quote(...), $foreignKey->columns)) . ')'
            . ' REFERENCES ' . SqliteIdentifier::quote($foreignKey->referencedTable)
            . '(' . implode(', ', array_map(SqliteIdentifier::quote(...), $foreignKey->referencedColumns)) . ')';

        if ($foreignKey->onDelete !== null) {
            $sql .= " ON DELETE $foreignKey->onDelete";
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= " ON UPDATE $foreignKey->onUpdate";
        }

        return $sql;
    }

    private function quoteString(
        string $value,
    ): string {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
