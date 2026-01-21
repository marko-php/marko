<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Sql;

use Marko\Database\Diff\SchemaDiff;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Diff\TableDiff;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
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
    ];

    /**
     * SQL expression keywords that should not be quoted.
     *
     * @var array<string>
     */
    private const array SQL_EXPRESSIONS = [
        'CURRENT_TIMESTAMP',
        'CURRENT_DATE',
        'CURRENT_TIME',
        'NOW()',
        'NULL',
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
            $statements = [...$statements, ...$this->generateTableAlterStatements($tableDiff)];
        }

        return $statements;
    }

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
            $statements = [...$statements, ...$this->generateReverseTableAlterStatements($tableDiff)];
        }

        return $statements;
    }

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

    public function generateAddColumn(
        string $table,
        Column $column,
    ): string {
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

    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string {
        return sprintf(
            'ALTER TABLE %s MODIFY COLUMN %s',
            $this->quote($table),
            $this->buildColumnDefinition($column),
        );
    }

    public function generateAddIndex(
        string $table,
        Index $index,
    ): string {
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
     * Quote an identifier with backticks.
     */
    private function quote(
        string $identifier,
    ): string {
        return '`' . $identifier . '`';
    }

    /**
     * Build a column definition for use in CREATE TABLE or ALTER TABLE.
     */
    private function buildColumnDefinition(
        Column $column,
    ): string {
        $parts = [$this->quote($column->name)];

        // Get MySQL type
        $mysqlType = $this->mapType($column->type, $column->length);
        $parts[] = $mysqlType;

        // NULL/NOT NULL
        $parts[] = $column->nullable ? 'NULL' : 'NOT NULL';

        // AUTO_INCREMENT (must come before DEFAULT)
        if ($column->autoIncrement) {
            $parts[] = 'AUTO_INCREMENT';
        }

        // DEFAULT value
        if ($column->default !== null && !$column->autoIncrement) {
            $parts[] = 'DEFAULT ' . $this->formatDefault($column->default);
        }

        // UNIQUE constraint (inline)
        if ($column->unique && !$column->primaryKey) {
            $parts[] = 'UNIQUE';
        }

        return implode(' ', $parts);
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

        // VARCHAR requires length
        if ($mysqlType === 'VARCHAR' && $length !== null) {
            return "VARCHAR($length)";
        }

        return $mysqlType;
    }

    /**
     * Format a default value for SQL.
     */
    private function formatDefault(
        mixed $default,
    ): string {
        // Check if it's a SQL expression
        if (is_string($default) && $this->isSqlExpression($default)) {
            return $default;
        }

        // Boolean values
        if (is_bool($default)) {
            return $default ? '1' : '0';
        }

        // Numeric values
        if (is_int($default) || is_float($default)) {
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

    /**
     * Check if a string is a SQL expression.
     */
    private function isSqlExpression(
        string $value,
    ): bool {
        $upperValue = strtoupper($value);

        return array_any(
            self::SQL_EXPRESSIONS,
            fn ($expression) => $upperValue === $expression || str_starts_with($upperValue, $expression),
        );
    }

    /**
     * Build index definition for inline use in CREATE TABLE.
     */
    private function buildIndexDefinition(
        Index $index,
    ): string {
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
     */
    private function generateTableAlterStatements(
        TableDiff $tableDiff,
    ): array {
        $statements = [];

        // Drop foreign keys first (to allow column drops)
        foreach ($tableDiff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($tableDiff->tableName, $foreignKey->name);
        }

        // Drop indexes
        foreach ($tableDiff->indexesToDrop as $index) {
            $statements[] = $this->generateDropIndex($tableDiff->tableName, $index->name);
        }

        // Drop columns
        foreach ($tableDiff->columnsToDrop as $column) {
            $statements[] = $this->generateDropColumn($tableDiff->tableName, $column->name);
        }

        // Add columns
        foreach ($tableDiff->columnsToAdd as $column) {
            $statements[] = $this->generateAddColumn($tableDiff->tableName, $column);
        }

        // Modify columns
        foreach ($tableDiff->columnsToModify as $columnName => $column) {
            // For modify, we need the old column - create a placeholder
            $oldColumn = new Column(name: $columnName, type: 'string');
            $statements[] = $this->generateModifyColumn($tableDiff->tableName, $column, $oldColumn);
        }

        // Add indexes
        foreach ($tableDiff->indexesToAdd as $index) {
            $statements[] = $this->generateAddIndex($tableDiff->tableName, $index);
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
     */
    private function generateReverseTableAlterStatements(
        TableDiff $tableDiff,
    ): array {
        $statements = [];

        // Reverse: drop foreign keys that were added
        foreach ($tableDiff->foreignKeysToAdd as $foreignKey) {
            $statements[] = $this->generateDropForeignKey($tableDiff->tableName, $foreignKey->name);
        }

        // Reverse: drop indexes that were added
        foreach ($tableDiff->indexesToAdd as $index) {
            $statements[] = $this->generateDropIndex($tableDiff->tableName, $index->name);
        }

        // Reverse: drop columns that were added
        foreach ($tableDiff->columnsToAdd as $column) {
            $statements[] = $this->generateDropColumn($tableDiff->tableName, $column->name);
        }

        // Reverse: add columns that were dropped
        foreach ($tableDiff->columnsToDrop as $column) {
            $statements[] = $this->generateAddColumn($tableDiff->tableName, $column);
        }

        // Reverse: add indexes that were dropped
        foreach ($tableDiff->indexesToDrop as $index) {
            $statements[] = $this->generateAddIndex($tableDiff->tableName, $index);
        }

        // Reverse: add foreign keys that were dropped
        foreach ($tableDiff->foreignKeysToDrop as $foreignKey) {
            $statements[] = $this->generateAddForeignKey($tableDiff->tableName, $foreignKey);
        }

        return $statements;
    }
}
