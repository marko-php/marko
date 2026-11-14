<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Sql;

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
 * PostgreSQL-specific SQL generator for schema migrations.
 *
 * Generates DDL statements using PostgreSQL syntax including:
 * - SERIAL/BIGSERIAL for auto-increment
 * - Double quotes for identifier quoting
 * - PostgreSQL-specific data types (JSONB, BYTEA, etc.)
 */
class PgSqlGenerator implements SqlGeneratorInterface
{
    /**
     * Type mapping from abstract Column types to PostgreSQL data types.
     *
     * @var array<string, string>
     */
    private const array TYPE_MAP = [
        'integer' => 'INTEGER',
        'int' => 'INTEGER',
        'bigint' => 'BIGINT',
        'smallint' => 'SMALLINT',
        'tinyint' => 'SMALLINT',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'boolean' => 'BOOLEAN',
        'bool' => 'BOOLEAN',
        'datetime' => 'TIMESTAMP',
        'timestamp' => 'TIMESTAMP',
        'date' => 'DATE',
        'time' => 'TIME',
        'decimal' => 'DECIMAL(10,2)',
        'float' => 'REAL',
        'double' => 'DOUBLE PRECISION',
        'json' => 'JSONB',
        'uuid' => 'UUID',
        'binary' => 'BYTEA',
        'blob' => 'BYTEA',
        'enum' => 'VARCHAR',
    ];

    /**
     * The column types a sequence can take (`CREATE SEQUENCE ... AS`).
     */
    private const array SEQUENCE_TYPES = ['SMALLINT', 'INTEGER', 'BIGINT'];

    public function generateUp(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        // Create new tables (with their indexes and foreign keys)
        foreach ($diff->tablesToCreate as $table) {
            $statements[] = $this->generateCreateTable($table);

            foreach ($table->indexes as $index) {
                $statements[] = $this->generateAddIndex($table->name, $index);
            }

            foreach ($table->foreignKeys as $foreignKey) {
                $statements[] = $this->generateAddForeignKey($table->name, $foreignKey);
            }
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

    public function generateDown(
        SchemaDiff $diff,
    ): array {
        $statements = [];

        // Reverse table creations (drop them)
        foreach ($diff->tablesToCreate as $table) {
            $statements[] = $this->generateDropTable($table->name);
        }

        // Reverse table drops (recreate them)
        foreach ($diff->tablesToDrop as $table) {
            $statements[] = $this->generateCreateTable($table);
        }

        // Reverse table alterations
        foreach ($diff->tablesToAlter as $tableDiff) {
            $statements = [...$statements, ...$this->generateReverseTableAlterations($tableDiff)];
        }

        return $statements;
    }

    public function generateCreateTable(
        Table $table,
    ): string {
        $columns = [];

        foreach ($table->columns as $column) {
            $columns[] = $this->generateColumnDefinition($column);
        }

        $columnsSql = implode(",\n    ", $columns);

        return 'CREATE TABLE ' . $this->quote($table->name) . " (\n    $columnsSql\n)";
    }

    public function generateDropTable(
        string $tableName,
    ): string {
        return 'DROP TABLE ' . $this->quote($tableName);
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

        $definition = $this->generateColumnDefinition($column, forAlter: true);

        return 'ALTER TABLE ' . $this->quote($table) . " ADD COLUMN $definition";
    }

    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string {
        return 'ALTER TABLE ' . $this->quote($table) . ' DROP COLUMN ' . $this->quote($columnName);
    }

    /**
     * @throws MigrationException When nothing PostgreSQL can alter in place differs, or the primary key or
     *                            auto-increment changes
     */
    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string {
        return $this->generateModifyColumnIfChanged($table, $column, $oldColumn)
            ?? throw MigrationException::nothingToModify($table, $column->name, 'PostgreSQL');
    }

    /**
     * A CREATE INDEX statement, or for an index that backs a unique constraint (restored by a down migration)
     * the ALTER TABLE ... ADD CONSTRAINT that recreates the constraint.
     */
    public function generateAddIndex(
        string $table,
        Index $index,
    ): string {
        if ($index->constraint) {
            $constraintColumns = implode(', ', $this->quoteIdentifiers($index->columns));

            return 'ALTER TABLE ' . $this->quote($table) . ' ADD CONSTRAINT ' . $this->quote($index->name)
                . " UNIQUE ($constraintColumns)";
        }

        $unique = $index->type === IndexType::Unique ? 'UNIQUE ' : '';
        $columns = $this->quoteIdentifiers($index->columns);
        $columnsSql = implode(', ', $columns);

        $whereSql = $index->where !== null ? " WHERE $index->where" : '';

        return "CREATE {$unique}INDEX " . $this->quote($index->name) . ' ON ' . $this->quote($table)
            . " ($columnsSql)$whereSql";
    }

    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string {
        // PostgreSQL indexes are not table-scoped, so we don't need the table name
        return 'DROP INDEX ' . $this->quote($indexName);
    }

    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string {
        $columns = $this->quoteIdentifiers($foreignKey->columns);
        $columnsSql = implode(', ', $columns);

        $referencedColumns = $this->quoteIdentifiers($foreignKey->referencedColumns);
        $referencedColumnsSql = implode(', ', $referencedColumns);

        $sql = 'ALTER TABLE ' . $this->quote($table) . ' ADD CONSTRAINT ' . $this->quote($foreignKey->name) . ' ';
        $sql .= "FOREIGN KEY ($columnsSql) ";
        $sql .= 'REFERENCES ' . $this->quote($foreignKey->referencedTable) . " ($referencedColumnsSql)";

        if ($foreignKey->onDelete !== null) {
            $sql .= " ON DELETE $foreignKey->onDelete";
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= " ON UPDATE $foreignKey->onUpdate";
        }

        return $sql;
    }

    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string {
        return 'ALTER TABLE ' . $this->quote($table) . ' DROP CONSTRAINT ' . $this->quote($keyName);
    }

    /**
     * Generate column definition SQL.
     *
     * @param Column $column The column to generate SQL for
     * @param bool $forAlter Whether this is for an ALTER TABLE statement
     */
    private function generateColumnDefinition(
        Column $column,
        bool $forAlter = false,
    ): string {
        $parts = [$this->quote($column->name)];

        // Handle auto-increment with SERIAL types
        if ($column->autoIncrement) {
            $parts[] = $this->getSerialType($column->type);
        } else {
            $parts[] = $this->mapType($column);
        }

        // NOT NULL constraint (not needed for PRIMARY KEY or nullable columns)
        if (
            !$column->nullable
            && !$column->primaryKey
            && !$column->autoIncrement
        ) {
            $parts[] = 'NOT NULL';
        }

        // DEFAULT value
        if ($column->default !== null) {
            $parts[] = 'DEFAULT ' . $this->formatDefaultValue($column->default);
        }

        // UNIQUE constraint
        if (
            $column->unique
            && !$column->primaryKey
        ) {
            $parts[] = 'UNIQUE';
        }

        // PRIMARY KEY (only in CREATE TABLE context, not ALTER)
        if (
            $column->primaryKey
            && !$forAlter
        ) {
            $parts[] = 'PRIMARY KEY';
        }

        return implode(' ', $parts);
    }

    /**
     * Map abstract column type to PostgreSQL data type.
     */
    private function mapType(
        Column $column,
    ): string {
        $baseType = self::TYPE_MAP[$column->type] ?? strtoupper($column->type);

        // Add length for VARCHAR - default to 255 if not specified
        if ($baseType === 'VARCHAR') {
            $length = $column->length ?? 255;

            return "VARCHAR($length)";
        }

        return $baseType;
    }

    /**
     * Get the SERIAL type for auto-increment columns.
     */
    private function getSerialType(
        string $type,
    ): string {
        return match ($type) {
            'bigint' => 'BIGSERIAL',
            'smallint' => 'SMALLSERIAL',
            default => 'SERIAL',
        };
    }

    /**
     * Format a default value for SQL: an Expression (or a shortcut string such as `gen_random_uuid()`) as
     * written, a Literal or any other string quoted.
     */
    private function formatDefaultValue(
        mixed $value,
    ): string {
        if ($value instanceof Expression) {
            return $value->sql;
        }

        if ($value instanceof Literal) {
            return $this->quoteString($value->value);
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (
            is_int($value)
            || is_float($value)
        ) {
            return (string) $value;
        }

        if (is_string($value)) {
            return Expression::isShortcut($value) ? $value : $this->quoteString($value);
        }

        return 'NULL';
    }

    private function quoteString(
        string $value,
    ): string {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Quote multiple identifiers.
     *
     * @param array<string> $identifiers
     * @return array<string>
     */
    private function quoteIdentifiers(
        array $identifiers,
    ): array {
        return array_map(
            fn (string $identifier): string => $this->quote($identifier),
            $identifiers,
        );
    }

    /**
     * Generate ALTER TABLE statements for a table diff.
     *
     * @return array<string>
     */
    private function generateTableAlterations(
        TableDiff $diff,
    ): array {
        $diff->assertSupportedPrimaryKeyChange('PostgreSQL');

        // Add columns, primary key columns together with their key
        $statements = $this->addColumnStatements($diff->tableName, $diff->columnsToAdd);

        // Drop columns
        foreach ($diff->columnsToDrop as $column) {
            $statements[] = $this->generateDropColumn($diff->tableName, $column->name);
        }

        // Modify columns
        $statements = [...$statements, ...$this->generateColumnModifications($diff, reverse: false)];

        // Add indexes
        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateAddIndex($diff->tableName, $index);
        }

        // Drop indexes
        foreach ($diff->indexesToDrop as $index) {
            $statements[] = $this->dropIndexStatement($diff->tableName, $index);
        }

        // Add foreign keys
        foreach ($diff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($diff->tableName, $foreignKey);
        }

        // Drop foreign keys
        foreach ($diff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($diff->tableName, $foreignKey->name);
        }

        return $statements;
    }

    /**
     * ADD COLUMN statements for $columns. The primary key columns and their ADD PRIMARY KEY share one ALTER
     * TABLE, at the position of the first of them, so a failure never leaves a key column behind without its
     * key.
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
     * One ALTER TABLE adding the primary key columns and the key over them. ADD PRIMARY KEY makes the columns
     * NOT NULL.
     *
     * @param non-empty-list<Column> $keyColumns
     */
    private function generateAddKeyColumns(
        string $table,
        array $keyColumns,
    ): string {
        $additions = array_map(
            fn (Column $column): string => 'ADD COLUMN ' . $this->generateColumnDefinition($column, forAlter: true),
            $keyColumns,
        );
        $keyNames = $this->quoteIdentifiers(
            array_map(static fn (Column $column): string => $column->name, $keyColumns),
        );
        $additions[] = 'ADD PRIMARY KEY (' . implode(', ', $keyNames) . ')';

        return 'ALTER TABLE ' . $this->quote($table) . ' ' . implode(', ', $additions);
    }

    /**
     * DROP INDEX, or DROP CONSTRAINT for an index that backs a unique constraint (PostgreSQL refuses to drop it
     * as an index).
     */
    private function dropIndexStatement(
        string $table,
        Index $index,
    ): string {
        return $index->constraint
            ? 'ALTER TABLE ' . $this->quote($table) . ' DROP CONSTRAINT ' . $this->quote($index->name)
            : $this->generateDropIndex($table, $index->name);
    }

    /**
     * Generate reverse ALTER TABLE statements for a table diff.
     *
     * @return array<string>
     */
    private function generateReverseTableAlterations(
        TableDiff $diff,
    ): array {
        $statements = [];

        // Reverse: drop added foreign keys
        foreach ($diff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($diff->tableName, $foreignKey->name);
        }

        // Reverse: drop added indexes
        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateDropIndex($diff->tableName, $index->name);
        }

        // Reverse: drop added columns
        foreach ($diff->columnsToAdd as $column) {
            $statements[] = $this->generateDropColumn($diff->tableName, $column->name);
        }

        // Reverse: add dropped columns, primary key columns together with their key
        $statements = [...$statements, ...$this->addColumnStatements($diff->tableName, $diff->columnsToDrop)];

        // Reverse: restore modified columns before the indexes and foreign keys that rely on them
        $statements = [...$statements, ...$this->generateColumnModifications($diff, reverse: true)];

        // Reverse: add dropped indexes
        foreach ($diff->indexesToDrop as $index) {
            $statements[] = $this->generateAddIndex($diff->tableName, $index);
        }

        // Reverse: add dropped foreign keys
        foreach ($diff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($diff->tableName, $foreignKey);
        }

        return $statements;
    }

    /**
     * ALTER TABLE statements that apply (or, in reverse, undo) every modified column of a table diff.
     *
     * @return list<string>
     * @throws MigrationException When the diff holds no previous definition for a modified column, or
     *                            the primary key or auto-increment changes
     */
    private function generateColumnModifications(
        TableDiff $diff,
        bool $reverse,
    ): array {
        $statements = [];

        foreach ($diff->columnsToModify as $columnName => $column) {
            $previous = $diff->previousColumn($columnName);
            // The diff's tolerances (an undeclared length or default keeps the database's) live in Column
            $target = $column->resolveAgainst($previous);

            $statement = $reverse
                ? $this->generateModifyColumnIfChanged($diff->tableName, $previous, $target)
                : $this->generateModifyColumnIfChanged($diff->tableName, $target, $previous);

            if ($statement !== null) {
                $statements[] = $statement;
            }
        }

        return $statements;
    }

    /**
     * The ALTER TABLE statement that turns $oldColumn into $column, or null when the only difference
     * is one the index diff applies (uniqueness) or that needs no DDL (such as an unspecified length).
     * An auto-increment column whose integer type changes gets a DO block that changes its sequence too.
     *
     * @throws MigrationException When the primary key or auto-increment changes
     */
    private function generateModifyColumnIfChanged(
        string $table,
        Column $column,
        Column $oldColumn,
    ): ?string {
        if ($column->primaryKey !== $oldColumn->primaryKey) {
            throw MigrationException::columnChangeNotSupported($table, $column->name, 'PostgreSQL', 'primary key');
        }

        if ($column->autoIncrement !== $oldColumn->autoIncrement) {
            throw MigrationException::columnChangeNotSupported($table, $column->name, 'PostgreSQL', 'auto-increment');
        }

        $alterations = [];

        $newType = $this->mapType($column);
        $typeChanges = $newType !== $this->mapType($oldColumn);

        // An auto-increment column's default is its sequence, which is never altered here
        $manageDefault = !$column->autoIncrement;

        // The old default goes before the type changes, so one that cannot be cast to the new type never
        // blocks the migration; the target default is set again after it
        $dropsDefaultForType = $typeChanges
            && $manageDefault
            && $oldColumn->default !== null;
        $columnName = $this->quote($column->name);

        if ($dropsDefaultForType) {
            $alterations[] = "ALTER COLUMN $columnName DROP DEFAULT";
        }

        if ($typeChanges) {
            // An explicit cast: PostgreSQL applies only an assignment cast without USING, which refuses
            // conversions such as varchar to integer
            $alterations[] = "ALTER COLUMN $columnName TYPE $newType USING $columnName::$newType";
        }

        // A primary key column is always NOT NULL, whatever the PHP property allows
        if (
            $column->nullable !== $oldColumn->nullable
            && !$column->primaryKey
        ) {
            $nullability = $column->nullable ? 'DROP NOT NULL' : 'SET NOT NULL';
            $alterations[] = "ALTER COLUMN $columnName $nullability";
        }

        if ($dropsDefaultForType) {
            if ($column->default !== null) {
                $alterations[] = "ALTER COLUMN $columnName SET DEFAULT "
                    . $this->formatDefaultValue($column->default);
            }
        } elseif (
            $manageDefault
            && !$column->hasSameDefaultAs($oldColumn)
        ) {
            $alterations[] = $column->default === null
                ? "ALTER COLUMN $columnName DROP DEFAULT"
                : "ALTER COLUMN $columnName SET DEFAULT " . $this->formatDefaultValue($column->default);
        }

        if ($alterations === []) {
            return null;
        }

        $alterTable = 'ALTER TABLE ' . $this->quote($table) . ' ' . implode(', ', $alterations);

        if (
            $typeChanges
            && $column->autoIncrement
            && in_array($newType, self::SEQUENCE_TYPES, true)
        ) {
            return $this->generateSequenceFollowingTypeChange($table, $column->name, $newType, $alterTable);
        }

        return $alterTable;
    }

    /**
     * One DO block that changes an auto-increment column's type together with the sequence that feeds it.
     *
     * A serial column's sequence keeps its own type (and the MAXVALUE that comes with it), so widening only
     * the column would still stop at the old type's limit. The sequence is found at run time because the
     * generator has no connection and the introspected column does not carry its name. One statement keeps
     * the column and the sequence in step: when either part fails, neither changes. An identity column's
     * sequence already follows the column, so changing it again is a no-op.
     */
    private function generateSequenceFollowingTypeChange(
        string $table,
        string $columnName,
        string $sequenceType,
        string $alterTable,
    ): string {
        $quotedTable = $this->quote($table);
        $quotedColumn = $this->quote($columnName);
        // pg_get_serial_sequence() parses its table argument as SQL, so it gets the quoted name; the column
        // argument is taken as written
        $tableLiteral = $this->quoteStringLiteral($quotedTable);
        $columnLiteral = $this->quoteStringLiteral($columnName);
        $message = $this->quoteStringLiteral(
            "Column $quotedColumn of table $quotedTable is auto-increment, but no sequence is owned by it, "
            . 'so its sequence cannot change type with it. Make the sequence that feeds it owned by the column '
            . "(ALTER SEQUENCE ... OWNED BY $quotedTable.$quotedColumn), then run the migration again.",
        );

        $body = <<<SQL
            DECLARE
                sequence_name text := pg_get_serial_sequence($tableLiteral, $columnLiteral);
            BEGIN
                IF sequence_name IS NULL THEN
                    RAISE EXCEPTION USING MESSAGE = $message;
                END IF;
                $alterTable;
                EXECUTE format('ALTER SEQUENCE %s AS $sequenceType', sequence_name);
            END
            SQL;
        $tag = $this->dollarQuoteTag($body);

        return "DO $tag\n$body\n$tag";
    }

    /**
     * A dollar-quote tag that does not occur in $body, so a table or column name holding `$$` (or the tag
     * itself) cannot end the quoted block early: `$$` when that is safe, otherwise `$marko$`, `$marko_1$`, ...
     */
    private function dollarQuoteTag(
        string $body,
    ): string {
        $tag = '$$';

        for ($attempt = 0; str_contains($body, $tag); $attempt++) {
            $tag = $attempt === 0 ? '$marko$' : '$marko_' . $attempt . '$';
        }

        return $tag;
    }

    /**
     * Quote an identifier through the driver's one quoting rule, PgSqlIdentifier.
     */
    private function quote(
        string $identifier,
    ): string {
        return PgSqlIdentifier::quote($identifier);
    }

    /**
     * A PostgreSQL string literal holding $value.
     */
    private function quoteStringLiteral(
        string $value,
    ): string {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
