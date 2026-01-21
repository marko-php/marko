<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Sql;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
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
    private const TYPE_MAP = [
        'integer' => 'INTEGER',
        'bigint' => 'BIGINT',
        'smallint' => 'SMALLINT',
        'string' => 'VARCHAR',
        'text' => 'TEXT',
        'boolean' => 'BOOLEAN',
        'datetime' => 'TIMESTAMP',
        'date' => 'DATE',
        'time' => 'TIME',
        'decimal' => 'DECIMAL',
        'float' => 'REAL',
        'double' => 'DOUBLE PRECISION',
        'json' => 'JSONB',
        'uuid' => 'UUID',
        'binary' => 'BYTEA',
    ];

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

        return "CREATE TABLE \"{$table->name}\" (\n    {$columnsSql}\n)";
    }

    public function generateDropTable(
        string $tableName,
    ): string {
        return "DROP TABLE \"{$tableName}\"";
    }

    public function generateAddColumn(
        string $table,
        Column $column,
    ): string {
        $definition = $this->generateColumnDefinition($column, forAlter: true);

        return "ALTER TABLE \"{$table}\" ADD COLUMN {$definition}";
    }

    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string {
        return "ALTER TABLE \"{$table}\" DROP COLUMN \"{$columnName}\"";
    }

    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string {
        $alterations = [];

        // Check for type change
        $newType = $this->mapType($column);
        $oldType = $this->mapType($oldColumn);

        if ($newType !== $oldType) {
            $alterations[] = "ALTER COLUMN \"{$column->name}\" TYPE {$newType}";
        }

        // Check for nullability change
        if ($column->nullable !== $oldColumn->nullable) {
            if ($column->nullable) {
                $alterations[] = "ALTER COLUMN \"{$column->name}\" DROP NOT NULL";
            } else {
                $alterations[] = "ALTER COLUMN \"{$column->name}\" SET NOT NULL";
            }
        }

        // Check for default change
        if ($column->default !== $oldColumn->default) {
            if ($column->default === null) {
                $alterations[] = "ALTER COLUMN \"{$column->name}\" DROP DEFAULT";
            } else {
                $defaultValue = $this->formatDefaultValue($column->default, $column->type);
                $alterations[] = "ALTER COLUMN \"{$column->name}\" SET DEFAULT {$defaultValue}";
            }
        }

        $alterationsSql = implode(', ', $alterations);

        return "ALTER TABLE \"{$table}\" {$alterationsSql}";
    }

    public function generateAddIndex(
        string $table,
        Index $index,
    ): string {
        $unique = $index->type === IndexType::Unique ? 'UNIQUE ' : '';
        $columns = $this->quoteIdentifiers($index->columns);
        $columnsSql = implode(', ', $columns);

        return "CREATE {$unique}INDEX \"{$index->name}\" ON \"{$table}\" ({$columnsSql})";
    }

    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string {
        // PostgreSQL indexes are not table-scoped, so we don't need the table name
        return "DROP INDEX \"{$indexName}\"";
    }

    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string {
        $columns = $this->quoteIdentifiers($foreignKey->columns);
        $columnsSql = implode(', ', $columns);

        $referencedColumns = $this->quoteIdentifiers($foreignKey->referencedColumns);
        $referencedColumnsSql = implode(', ', $referencedColumns);

        $sql = "ALTER TABLE \"{$table}\" ADD CONSTRAINT \"{$foreignKey->name}\" ";
        $sql .= "FOREIGN KEY ({$columnsSql}) ";
        $sql .= "REFERENCES \"{$foreignKey->referencedTable}\" ({$referencedColumnsSql})";

        if ($foreignKey->onDelete !== null) {
            $sql .= " ON DELETE {$foreignKey->onDelete}";
        }

        if ($foreignKey->onUpdate !== null) {
            $sql .= " ON UPDATE {$foreignKey->onUpdate}";
        }

        return $sql;
    }

    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string {
        return "ALTER TABLE \"{$table}\" DROP CONSTRAINT \"{$keyName}\"";
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
        $parts = ["\"{$column->name}\""];

        // Handle auto-increment with SERIAL types
        if ($column->autoIncrement) {
            $parts[] = $this->getSerialType($column->type);
        } else {
            $parts[] = $this->mapType($column);
        }

        // NOT NULL constraint (not needed for PRIMARY KEY or nullable columns)
        if (!$column->nullable && !$column->primaryKey && !$column->autoIncrement) {
            $parts[] = 'NOT NULL';
        }

        // DEFAULT value
        if ($column->default !== null) {
            $parts[] = 'DEFAULT ' . $this->formatDefaultValue($column->default, $column->type);
        }

        // UNIQUE constraint
        if ($column->unique && !$column->primaryKey) {
            $parts[] = 'UNIQUE';
        }

        // PRIMARY KEY (only in CREATE TABLE context, not ALTER)
        if ($column->primaryKey && !$forAlter) {
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

        // Add length for VARCHAR
        if ($column->type === 'string' && $column->length !== null) {
            return "{$baseType}({$column->length})";
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
     * Format a default value for SQL.
     */
    private function formatDefaultValue(
        mixed $value,
        string $type,
    ): string {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // Escape single quotes
            $escaped = str_replace("'", "''", $value);

            return "'{$escaped}'";
        }

        return 'NULL';
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
            static fn (string $identifier): string => "\"{$identifier}\"",
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
        $statements = [];

        // Add columns
        foreach ($diff->columnsToAdd as $column) {
            $statements[] = $this->generateAddColumn($diff->tableName, $column);
        }

        // Drop columns
        foreach ($diff->columnsToDrop as $column) {
            $statements[] = $this->generateDropColumn($diff->tableName, $column->name);
        }

        // Modify columns (note: we don't have the old column in TableDiff,
        // so we generate a type change only)
        foreach ($diff->columnsToModify as $columnName => $column) {
            // Without the old column, we can only do a basic type alteration
            $statements[] = "ALTER TABLE \"{$diff->tableName}\" ALTER COLUMN \"{$columnName}\" TYPE " . $this->mapType(
                $column
            );
        }

        // Add indexes
        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateAddIndex($diff->tableName, $index);
        }

        // Drop indexes
        foreach ($diff->indexesToDrop as $index) {
            $statements[] = $this->generateDropIndex($diff->tableName, $index->name);
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
     * Generate reverse ALTER TABLE statements for a table diff.
     *
     * @return array<string>
     */
    private function generateReverseTableAlterations(
        TableDiff $diff,
    ): array {
        $statements = [];

        // Reverse: drop added columns
        foreach ($diff->columnsToAdd as $column) {
            $statements[] = $this->generateDropColumn($diff->tableName, $column->name);
        }

        // Reverse: add dropped columns
        foreach ($diff->columnsToDrop as $column) {
            $statements[] = $this->generateAddColumn($diff->tableName, $column);
        }

        // Reverse: drop added indexes
        foreach ($diff->indexesToAdd as $index) {
            $statements[] = $this->generateDropIndex($diff->tableName, $index->name);
        }

        // Reverse: add dropped indexes
        foreach ($diff->indexesToDrop as $index) {
            $statements[] = $this->generateAddIndex($diff->tableName, $index);
        }

        // Reverse: drop added foreign keys
        foreach ($diff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($diff->tableName, $foreignKey->name);
        }

        // Reverse: add dropped foreign keys
        foreach ($diff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($diff->tableName, $foreignKey);
        }

        return $statements;
    }
}
