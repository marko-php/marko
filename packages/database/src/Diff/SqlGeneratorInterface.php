<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

/**
 * Generates SQL statements from schema diff objects.
 *
 * Driver-specific implementations convert SchemaDiff objects into
 * the appropriate SQL syntax for each database platform.
 */
interface SqlGeneratorInterface
{
    /**
     * Generate SQL statements to apply the schema changes (migrate up).
     *
     * @param SchemaDiff $diff The schema differences to apply
     * @return array<string> Array of SQL statements
     */
    public function generateUp(SchemaDiff $diff): array;

    /**
     * Generate SQL statements to reverse the schema changes (migrate down).
     *
     * @param SchemaDiff $diff The schema differences to reverse
     * @return array<string> Array of SQL statements
     */
    public function generateDown(SchemaDiff $diff): array;

    /**
     * Generate CREATE TABLE statement.
     *
     * @param Table $table The table definition
     * @return string The CREATE TABLE SQL statement
     */
    public function generateCreateTable(Table $table): string;

    /**
     * Generate DROP TABLE statement.
     *
     * @param string $tableName The name of the table to drop
     * @return string The DROP TABLE SQL statement
     */
    public function generateDropTable(string $tableName): string;

    /**
     * Generate ADD COLUMN statement.
     *
     * @param string $table The table name
     * @param Column $column The column definition to add
     * @return string The ALTER TABLE ADD COLUMN SQL statement
     */
    public function generateAddColumn(
        string $table,
        Column $column,
    ): string;

    /**
     * Generate DROP COLUMN statement.
     *
     * @param string $table The table name
     * @param string $columnName The name of the column to drop
     * @return string The ALTER TABLE DROP COLUMN SQL statement
     */
    public function generateDropColumn(
        string $table,
        string $columnName,
    ): string;

    /**
     * Generate MODIFY COLUMN statement.
     *
     * @param string $table The table name
     * @param Column $column The new column definition
     * @param Column $oldColumn The previous column definition
     * @return string The ALTER TABLE MODIFY COLUMN SQL statement
     */
    public function generateModifyColumn(
        string $table,
        Column $column,
        Column $oldColumn,
    ): string;

    /**
     * Generate ADD INDEX statement.
     *
     * @param string $table The table name
     * @param Index $index The index definition to add
     * @return string The CREATE INDEX SQL statement
     */
    public function generateAddIndex(
        string $table,
        Index $index,
    ): string;

    /**
     * Generate DROP INDEX statement.
     *
     * @param string $table The table name
     * @param string $indexName The name of the index to drop
     * @return string The DROP INDEX SQL statement
     */
    public function generateDropIndex(
        string $table,
        string $indexName,
    ): string;

    /**
     * Generate ADD FOREIGN KEY statement.
     *
     * @param string $table The table name
     * @param ForeignKey $foreignKey The foreign key definition to add
     * @return string The ALTER TABLE ADD CONSTRAINT SQL statement
     */
    public function generateAddForeignKey(
        string $table,
        ForeignKey $foreignKey,
    ): string;

    /**
     * Generate DROP FOREIGN KEY statement.
     *
     * @param string $table The table name
     * @param string $keyName The name of the foreign key to drop
     * @return string The ALTER TABLE DROP CONSTRAINT SQL statement
     */
    public function generateDropForeignKey(
        string $table,
        string $keyName,
    ): string;
}
