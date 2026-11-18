<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Sql;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\Expression;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Literal;
use Marko\Database\Schema\Table;

/**
 * MySQL-specific SQL generator that produces DDL statements from schema diffs.
 *
 * Handles MySQL's specific syntax for CREATE TABLE, ALTER TABLE, indexes,
 * and foreign keys using backticks for identifier quoting.
 */
class MySqlGenerator implements SqlGeneratorInterface
{
    /**
     * Type mapping from abstract types to MySQL data types.
     *
     * @var array<string, string>
     */
    private const array TYPE_MAP = [
        'integer' => 'INT',
        'int' => 'INT',
        'bigint' => 'BIGINT',
        'smallint' => 'SMALLINT',
        'tinyint' => 'TINYINT',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'boolean' => 'TINYINT(1)',
        'bool' => 'TINYINT(1)',
        'datetime' => 'DATETIME',
        'date' => 'DATE',
        'time' => 'TIME',
        'timestamp' => 'TIMESTAMP',
        'decimal' => 'DECIMAL(10,2)',
        'float' => 'FLOAT',
        'double' => 'DOUBLE',
        'blob' => 'BLOB',
        'binary' => 'BLOB',
        'json' => 'JSON',
        'uuid' => 'CHAR(36)',
        'enum' => 'VARCHAR',
    ];

    /**
     * Expression defaults MySQL accepts without parentheses: CURRENT_TIMESTAMP and its synonyms, with or
     * without a precision. Every other expression default must be parenthesized (MySQL 8.0.13+).
     */
    private const string BARE_EXPRESSION_PATTERN =
        '/^(?:(?:CURRENT_TIMESTAMP|LOCALTIMESTAMP|LOCALTIME)(?:\(\d*\))?|NOW\(\d*\))$/i';

    /**
     * Native base types whose length is part of the type, so a different length is a different type.
     *
     * @var list<string>
     */
    private const array LENGTH_TYPES = ['char', 'varchar', 'binary', 'varbinary'];

    /**
     * Native base types that carry a character set and collation.
     *
     * @var list<string>
     */
    private const array COLLATED_TYPES = [
        'char',
        'varchar',
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'enum',
        'set',
    ];

    /**
     * Native base types that accept ON UPDATE CURRENT_TIMESTAMP.
     *
     * @var list<string>
     */
    private const array ON_UPDATE_TYPES = ['timestamp', 'datetime'];

    /**
     * Native base types that cannot hold a literal DEFAULT.
     *
     * @var list<string>
     */
    private const array NO_LITERAL_DEFAULT_TYPES = [
        'tinytext',
        'text',
        'mediumtext',
        'longtext',
        'tinyblob',
        'blob',
        'mediumblob',
        'longblob',
        'json',
        'geometry',
    ];

    /**
     * @throws MigrationException
     */
    public function generateUp(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        // Create new tables
        foreach ($diff->tablesToCreate as $table) {
            $statements[] = $this->generateCreateTable($table);
        }

        // Drop tables
        foreach ($diff->tablesToDrop as $table) {
            $statements[] = $this->generateDropTable($table->name);
        }

        // Alter existing tables
        foreach ($diff->tablesToAlter as $tableDiff) {
            $statements = [...$statements, ...$this->generateTableAlterations($tableDiff)];
        }

        return $statements;
    }

    /**
     * @throws MigrationException
     */
    public function generateDown(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        // Reverse table creates by dropping them
        foreach ($diff->tablesToCreate as $table) {
            $statements[] = $this->generateDropTable($table->name);
        }

        // Reverse table drops by recreating them
        foreach ($diff->tablesToDrop as $table) {
            $statements[] = $this->generateCreateTable($table);
        }

        // Reverse table alterations
        foreach ($diff->tablesToAlter as $tableDiff) {
            $statements = [...$statements, ...$this->generateReverseTableAlterations($tableDiff)];
        }

        return $statements;
    }

    /**
     * @throws MigrationException
     */
    public function generateCreateTable(
        Table $table,
    ): string {
        $columnDefinitions = [];
        $primaryKeyColumns = [];

        foreach ($table->columns as $column) {
            $columnDefinitions[] = $this->buildColumnDefinition($column);

            if ($column->primaryKey) {
                $primaryKeyColumns[] = $this->quote($column->name);
            }
        }

        // Add primary key constraint if any
        if (!empty($primaryKeyColumns)) {
            $columnDefinitions[] = 'PRIMARY KEY (' . implode(', ', $primaryKeyColumns) . ')';
        }

        // Add indexes
        foreach ($table->indexes as $index) {
            $columnDefinitions[] = $this->buildIndexDefinition($index);
        }

        // Add foreign keys
        foreach ($table->foreignKeys as $foreignKey) {
            $columnDefinitions[] = $this->buildForeignKeyDefinition($foreignKey);
        }

        return sprintf(
            'CREATE TABLE %s (%s)',
            $this->quote($table->name),
            implode(', ', $columnDefinitions),
        );
    }

    public function generateDropTable(
        string $tableName,
    ): string {
        return sprintf('DROP TABLE %s', $this->quote($tableName));
    }

    /**
     * A primary key column is added together with its key (see generateAddKeyColumns()).
     */
    public function generateAddColumn(
        string $table,
        Column $column,
    ): string {
        if ($column->primaryKey) {
            return $this->generateAddKeyColumns($table, [$column]);
        }

        return sprintf(
            'ALTER TABLE %s ADD COLUMN %s',
            $this->quote($table),
            $this->buildColumnDefinition($column),
        );
    }

    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string {
        return sprintf(
            'ALTER TABLE %s DROP COLUMN %s',
            $this->quote($table),
            $this->quote($columnName),
        );
    }

    /**
     * MODIFY COLUMN restates the full definition of $column. Migrations generated from a diff pass the
     * resolved target (see generateColumnModifications()), so nothing the diff accepted is lost.
     */
    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string {
        return sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s',
            $this->quote($table),
            $this->buildColumnDefinition($column, inlineUnique: false),
        );
    }

    /**
     * @throws MigrationException
     */
    public function generateAddIndex(
        string $table,
        Index $index,
    ): string {
        $this->assertNotPartial($index);

        $indexType = match ($index->type) {
            IndexType::Unique => 'UNIQUE INDEX',
            IndexType::Fulltext => 'FULLTEXT INDEX',
            default => 'INDEX',
        };

        $columns = array_map(fn ($col) => $this->quote($col), $index->columns);

        return sprintf(
            'CREATE %s %s ON %s (%s)',
            $indexType,
            $this->quote($index->name),
            $this->quote($table),
            implode(', ', $columns),
        );
    }

    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string {
        return sprintf(
            'DROP INDEX %s ON %s',
            $this->quote($indexName),
            $this->quote($table),
        );
    }

    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string {
        $localColumns = array_map(fn ($col) => $this->quote($col), $foreignKey->columns);
        $refColumns = array_map(fn ($col) => $this->quote($col), $foreignKey->referencedColumns);

        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quote($table),
            $this->quote($foreignKey->name),
            implode(', ', $localColumns),
            $this->quote($foreignKey->referencedTable),
            implode(', ', $refColumns),
        );

        if ($foreignKey->onDelete !== null) {
            $sql .= ' ON DELETE ' . $foreignKey->onDelete;
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . $foreignKey->onUpdate;
        }

        return $sql;
    }

    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string {
        return sprintf(
            'ALTER TABLE %s DROP FOREIGN KEY %s',
            $this->quote($table),
            $this->quote($keyName),
        );
    }

    /**
     * Quote an identifier through the driver's one quoting rule, MySqlIdentifier.
     */
    private function quote(
        string $identifier,
    ): string {
        return MySqlIdentifier::quote($identifier);
    }

    /**
     * Build a column definition for use in CREATE TABLE or ALTER TABLE.
     */
    private function buildColumnDefinition(
        Column $column,
        bool $inlineUnique = true,
    ): string {
        $parts = [$this->quote($column->name)];

        // The native type the database reported wins: it holds what Column cannot (precision, UNSIGNED, ...)
        $mysqlType = $column->nativeType ?? $this->mapType($column->type, $column->length);
        $parts[] = $mysqlType;

        if (
            $column->collation !== null
            && $this->baseTypeIn($mysqlType, self::COLLATED_TYPES)
        ) {
            $parts[] = 'COLLATE ' . $column->collation;
        }

        // NULL/NOT NULL - PRIMARY KEY and AUTO_INCREMENT columns must be NOT NULL
        $forceNotNull = $column->primaryKey
            || $column->autoIncrement;
        $parts[] = ($column->nullable && !$forceNotNull)
            ? 'NULL'
            : 'NOT NULL';

        // AUTO_INCREMENT (must come before DEFAULT)
        if ($column->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }

        // DEFAULT value
        if (
            $column->default !== null
            && !$column->autoIncrement
        ) {
            $parts[] = 'DEFAULT ' . $this->formatDefault($column->default);
        }

        if (
            $column->onUpdateExpression !== null
            && $this->baseTypeIn($mysqlType, self::ON_UPDATE_TYPES)
        ) {
            $parts[] = 'ON UPDATE ' . $column->onUpdateExpression;
        }

        // UNIQUE constraint (inline). Never restated by MODIFY COLUMN, where it would add a second unique
        // index to a column that has one; the index diff owns uniqueness there.
        if (
            $inlineUnique
            && $column->unique
            && !$column->primaryKey
        ) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
    }

    /**
     * The base of a MySQL type, lowercased: `decimal` for `DECIMAL(12,4) UNSIGNED`.
     */
    private function baseType(
        string $type,
    ): string {
        preg_match('/^[a-z]+/i', $type, $matches);

        return strtolower($matches[0] ?? $type);
    }

    /**
     * @param list<string> $baseTypes
     */
    private function baseTypeIn(
        string $type,
        array $baseTypes,
    ): bool {
        return in_array($this->baseType($type), $baseTypes, true);
    }

    /**
     * The column an up migration moves $column to, given $previous (the database's definition).
     *
     * Column::resolveAgainst() applies the diff's tolerances (an undeclared length or default keeps the
     * database's) and carries the native metadata over. This keeps that metadata only where it still fits:
     * the native type while the entity does not redefine the type, the collation while the type is a
     * string type, and ON UPDATE while it is a timestamp or datetime.
     */
    private function targetColumn(
        Column $column,
        Column $previous,
    ): Column {
        $resolved = $column->resolveAgainst($previous);

        // A length is only part of the type for the length-bearing types (MySQL reports 65535 for TEXT)
        $previousIsSized = $previous->nativeType !== null
            ? $this->baseTypeIn($previous->nativeType, self::LENGTH_TYPES)
            : $this->baseTypeIn($this->mapType($previous->type, $previous->length), self::LENGTH_TYPES);
        $length = $column->length ?? ($previousIsSized ? $previous->length : null);

        $nativeType = $this->nativeTypeApplies($resolved, $length) ? $resolved->nativeType : null;
        $type = $nativeType ?? $this->mapType($resolved->type, $length);

        // A kept literal default goes when the entity changes the column to a type that cannot hold it
        // (VARCHAR to TEXT); the down migration restores it with the previous type. An expression default
        // fits any type (MySQL 8.0.13+), so it stays.
        $keptDefaultFits = $column->default !== null
            || $this->isExpressionDefault($resolved->default)
            || !$this->baseTypeIn($type, self::NO_LITERAL_DEFAULT_TYPES);

        return new Column(
            name: $resolved->name,
            type: $resolved->type,
            length: $length,
            nullable: $resolved->nullable,
            default: $keptDefaultFits ? $resolved->default : null,
            unique: $resolved->unique,
            primaryKey: $resolved->primaryKey,
            autoIncrement: $resolved->autoIncrement,
            references: $resolved->references,
            onDelete: $resolved->onDelete,
            onUpdate: $resolved->onUpdate,
            nativeType: $nativeType,
            collation: $this->baseTypeIn($type, self::COLLATED_TYPES) ? $resolved->collation : null,
            onUpdateExpression: $this->baseTypeIn($type, self::ON_UPDATE_TYPES) ? $resolved->onUpdateExpression : null,
        );
    }

    /**
     * Whether the native type carried over from the database still describes $column: the entity names
     * the same base type (`decimal` for `decimal(12,4) unsigned`, `enum` for `enum('a','b')`) and, for
     * char/varchar/binary/varbinary, the same length.
     */
    private function nativeTypeApplies(
        Column $column,
        ?int $length,
    ): bool {
        if ($column->nativeType === null) {
            return false;
        }

        $nativeBase = $this->normalizeBaseType($this->baseType($column->nativeType));
        $sameBase = in_array($nativeBase, [
            $this->normalizeBaseType($this->baseType($this->mapType($column->type, $length))),
            $this->normalizeBaseType(strtolower($column->type)),
        ], true);

        if (!$sameBase) {
            return false;
        }

        if (
            !in_array($nativeBase, self::LENGTH_TYPES, true)
            || $length === null
        ) {
            return true;
        }

        return preg_match('/\((\d+)\)/', $column->nativeType, $matches) === 1
            && (int) $matches[1] === $length;
    }

    /**
     * `datetime` and `timestamp` are the same type to the diff (Column::equals()), so they are here too.
     */
    private function normalizeBaseType(
        string $baseType,
    ): string {
        return $baseType === 'datetime' ? 'timestamp' : $baseType;
    }

    /**
     * Map abstract type to MySQL data type.
     */
    private function mapType(
        string $type,
        ?int $length,
    ): string {
        $lowerType = strtolower($type);
        $mysqlType = self::TYPE_MAP[$lowerType] ?? strtoupper($type);

        // VARCHAR requires length - default to 255 if not specified
        if ($mysqlType === 'VARCHAR') {
            $length ??= 255;

            return "VARCHAR($length)";
        }

        return $mysqlType;
    }

    /**
     * Format a default value for SQL: an Expression (or a shortcut string such as `UUID()`) as an expression
     * default, a Literal or any other string quoted.
     */
    private function formatDefault(
        mixed $default,
    ): string {
        if ($default instanceof Expression) {
            return $this->formatExpression($default->sql);
        }

        if ($default instanceof Literal) {
            return "'" . addslashes($default->value) . "'";
        }

        if (
            is_string($default)
            && Expression::isShortcut($default)
        ) {
            return $this->formatExpression($default);
        }

        // Kept from the original keyword list: a string 'NULL' is no default rather than the text NULL
        if (
            is_string($default)
            && strtoupper($default) === 'NULL'
        ) {
            return 'NULL';
        }

        // Boolean values
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }

        // Numeric values
        if (
            is_int($default)
            || is_float($default)
        ) {
            return (string) $default;
        }

        // String values - quote them
        if (is_string($default)) {
            return "'" . addslashes($default) . "'";
        }

        // Null
        if ($default === null) {
            return 'NULL';
        }

        return (string) $default;
    }

    private function isExpressionDefault(
        mixed $default,
    ): bool {
        return $default instanceof Expression
            || (
                is_string($default)
                && Expression::isShortcut($default)
            );
    }

    /**
     * An expression default as MySQL 8.0.13+ accepts it: the CURRENT_TIMESTAMP family as written, anything
     * else in parentheses (unless it already is).
     */
    private function formatExpression(
        string $sql,
    ): string {
        $sql = trim($sql);

        if (
            preg_match(self::BARE_EXPRESSION_PATTERN, $sql) === 1
            || $this->isParenthesized($sql)
        ) {
            return $sql;
        }

        return "($sql)";
    }

    /**
     * Whether the first character opens a parenthesis that the last character closes.
     */
    private function isParenthesized(
        string $sql,
    ): bool {
        if (
            !str_starts_with($sql, '(')
            || !str_ends_with($sql, ')')
        ) {
            return false;
        }

        $depth = 0;
        $lastIndex = strlen($sql) - 1;

        for ($index = 0; $index <= $lastIndex; $index++) {
            $depth += match ($sql[$index]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if (
                $depth === 0
                && $index < $lastIndex
            ) {
                return false;
            }
        }

        return $depth === 0;
    }

    /**
     * MySQL has no partial indexes; refuse loudly rather than silently creating a full index.
     *
     * @throws MigrationException
     */
    private function assertNotPartial(
        Index $index,
    ): void {
        if ($index->where !== null) {
            throw MigrationException::partialIndexNotSupported($index->name, 'MySQL');
        }
    }

    /**
     * Build index definition for inline use in CREATE TABLE.
     *
     * @throws MigrationException
     */
    private function buildIndexDefinition(
        Index $index,
    ): string {
        $this->assertNotPartial($index);

        $indexType = match ($index->type) {
            IndexType::Unique => 'UNIQUE INDEX',
            IndexType::Fulltext => 'FULLTEXT INDEX',
            default => 'INDEX',
        };

        $columns = array_map(fn ($col) => $this->quote($col), $index->columns);

        return sprintf(
            '%s %s (%s)',
            $indexType,
            $this->quote($index->name),
            implode(', ', $columns),
        );
    }

    /**
     * Build foreign key definition for inline use in CREATE TABLE.
     */
    private function buildForeignKeyDefinition(
        ForeignKey $foreignKey,
    ): string {
        $localColumns = array_map(fn ($col) => $this->quote($col), $foreignKey->columns);
        $refColumns = array_map(fn ($col) => $this->quote($col), $foreignKey->referencedColumns);

        $sql = sprintf(
            'CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s)',
            $this->quote($foreignKey->name),
            implode(', ', $localColumns),
            $this->quote($foreignKey->referencedTable),
            implode(', ', $refColumns),
        );

        if ($foreignKey->onDelete !== null) {
            $sql .= ' ON DELETE ' . $foreignKey->onDelete;
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . $foreignKey->onUpdate;
        }

        return $sql;
    }

    /**
     * Generate ALTER statements for a table diff.
     *
     * @return array<string>
     * @throws MigrationException
     */
    private function generateTableAlterations(
        TableDiff $tableDiff,
    ): array {
        $tableDiff->assertSupportedPrimaryKeyChange('MySQL');

        $statements = [];

        // Drop foreign keys first (to allow column drops)
        foreach ($tableDiff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($tableDiff->tableName, $foreignKey->name);
        }

        $replacementIndexes = $this->replacementIndexes($tableDiff);

        // A replacement index goes in before the index it replaces, which a foreign key may still need
        foreach ($replacementIndexes as $index) {
            $statements[] = $this->generateAddIndex($tableDiff->tableName, $index);
        }

        // Drop indexes
        foreach ($tableDiff->indexesToDrop as $index) {
            $statements[] = $this->generateDropIndex($tableDiff->tableName, $index->name);
        }

        // Drop columns
        foreach ($tableDiff->columnsToDrop as $column) {
            $statements[] = $this->generateDropColumn($tableDiff->tableName, $column->name);
        }

        // Add columns, primary key columns together with their key
        $statements = [...$statements, ...$this->addColumnStatements($tableDiff->tableName, $tableDiff->columnsToAdd)];

        // Modify columns
        $statements = [...$statements, ...$this->generateColumnModifications($tableDiff, reverse: false)];

        // Add indexes
        foreach ($tableDiff->indexesToAdd as $index) {
            if (!in_array($index, $replacementIndexes, true)) {
                $statements[] = $this->generateAddIndex($tableDiff->tableName, $index);
            }
        }

        // Add foreign keys last
        foreach ($tableDiff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($tableDiff->tableName, $foreignKey);
        }

        return $statements;
    }

    /**
     * Generate reverse ALTER statements for a table diff (for down migrations).
     *
     * @return array<string>
     * @throws MigrationException
     */
    private function generateReverseTableAlterations(
        TableDiff $tableDiff,
    ): array {
        $statements = [];

        // Reverse: drop foreign keys that were added
        foreach ($tableDiff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($tableDiff->tableName, $foreignKey->name);
        }

        $replacementIndexes = $this->replacementIndexes($tableDiff);

        // Reverse: drop indexes that were added (a replacement only once the index it replaced is back)
        foreach ($tableDiff->indexesToAdd as $index) {
            if (!in_array($index, $replacementIndexes, true)) {
                $statements[] = $this->generateDropIndex($tableDiff->tableName, $index->name);
            }
        }

        // Reverse: drop columns that were added
        foreach ($tableDiff->columnsToAdd as $column) {
            $statements[] = $this->generateDropColumn($tableDiff->tableName, $column->name);
        }

        // Reverse: add columns that were dropped, primary key columns together with their key
        $statements = [...$statements, ...$this->addColumnStatements(
            $tableDiff->tableName,
            $tableDiff->columnsToDrop,
        )];

        // Reverse: restore modified columns to their previous definition
        $statements = [...$statements, ...$this->generateColumnModifications($tableDiff, reverse: true)];

        // Reverse: add indexes that were dropped
        foreach ($tableDiff->indexesToDrop as $index) {
            $statements[] = $this->generateAddIndex($tableDiff->tableName, $index);
        }

        foreach ($replacementIndexes as $index) {
            $statements[] = $this->generateDropIndex($tableDiff->tableName, $index->name);
        }

        // Reverse: add foreign keys that were dropped
        foreach ($tableDiff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($tableDiff->tableName, $foreignKey);
        }

        return $statements;
    }

    /**
     * ADD COLUMN statements for $columns. The primary key columns and their ADD PRIMARY KEY share one ALTER
     * TABLE, at the position of the first of them: MySQL refuses an AUTO_INCREMENT column that is not a key
     * (error 1075), and one statement never leaves a key column behind without its key.
     *
     * @param array<Column> $columns
     * @return list<string>
     */
    private function addColumnStatements(
        string $table,
        array $columns,
    ): array {
        $statements = [];
        $keyColumns = array_values(array_filter($columns, static fn (Column $column): bool => $column->primaryKey));

        foreach ($columns as $column) {
            if (!$column->primaryKey) {
                $statements[] = $this->generateAddColumn($table, $column);
            } elseif ($column === $keyColumns[0]) {
                $statements[] = $this->generateAddKeyColumns($table, $keyColumns);
            }
        }

        return $statements;
    }

    /**
     * One ALTER TABLE adding the primary key columns and the key over them.
     *
     * @param non-empty-list<Column> $keyColumns
     */
    private function generateAddKeyColumns(
        string $table,
        array $keyColumns,
    ): string {
        $additions = array_map(
            fn (Column $column): string => 'ADD COLUMN ' . $this->buildColumnDefinition($column),
            $keyColumns,
        );
        $keyNames = array_map(fn (Column $column): string => $this->quote($column->name), $keyColumns);
        $additions[] = 'ADD PRIMARY KEY (' . implode(', ', $keyNames) . ')';

        return sprintf('ALTER TABLE %s %s', $this->quote($table), implode(', ', $additions));
    }

    /**
     * The added indexes that go in before any index is dropped, so a foreign key never loses the last index it uses
     * (InnoDB refuses that drop, error 1553): every added index, except one that redefines a dropped index under
     * the same name (it must follow the drop) and one on a column the diff adds or modifies (the column must be
     * in its final shape first). In down they are dropped only after the dropped indexes are restored.
     *
     * @return list<Index>
     */
    private function replacementIndexes(
        TableDiff $tableDiff,
    ): array {
        $droppedNames = array_map(static fn (Index $index): string => $index->name, $tableDiff->indexesToDrop);
        $changedColumnNames = [
            ...array_map(static fn (Column $column): string => $column->name, $tableDiff->columnsToAdd),
            ...array_keys($tableDiff->columnsToModify),
        ];

        return array_values(array_filter(
            $tableDiff->indexesToAdd,
            static fn (Index $index): bool => !in_array($index->name, $droppedNames, true)
                && array_intersect($index->columns, $changedColumnNames) === [],
        ));
    }

    /**
     * MODIFY COLUMN statements that apply (or, in reverse, undo) every modified column of a table diff.
     *
     * The up statement moves each column to its target (see targetColumn()); the down statement restates
     * the database's previous definition, native type, collation and ON UPDATE included. A column whose
     * target renders the same as its previous definition gets no statement in either direction.
     *
     * @return list<string>
     * @throws MigrationException When the diff holds no previous definition for a modified column, or a
     *                            modified column's primary key changes
     */
    private function generateColumnModifications(
        TableDiff $tableDiff,
        bool $reverse,
    ): array {
        $statements = [];

        foreach ($tableDiff->columnsToModify as $columnName => $column) {
            $previous = $tableDiff->previousColumn($columnName);
            $target = $this->targetColumn($column, $previous);

            // MODIFY COLUMN cannot add or remove a primary key, so the change would never apply
            if ($target->primaryKey !== $previous->primaryKey) {
                throw MigrationException::columnChangeNotSupported(
                    $tableDiff->tableName,
                    $target->name,
                    'MySQL',
                    'primary key',
                );
            }

            if ($this->buildColumnDefinition($target, inlineUnique: false)
                === $this->buildColumnDefinition($previous, inlineUnique: false)) {
                continue;
            }

            $statements[] = $reverse
                ? $this->generateModifyColumn($tableDiff->tableName, $previous, $target)
                : $this->generateModifyColumn($tableDiff->tableName, $target, $previous);
        }

        return $statements;
    }
}
