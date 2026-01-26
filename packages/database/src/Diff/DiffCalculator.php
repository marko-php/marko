<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

class DiffCalculator
{
    /**
     * Calculate the difference between entity-defined schema and database state.
     *
     * @param array<string, Table> $entitySchema Tables defined by entities
     * @param array<string, Table> $databaseSchema Tables in the database
     */
    public function calculate(
        array $entitySchema,
        array $databaseSchema,
    ): SchemaDiff {
        $tablesToCreate = [];
        $tablesToDrop = [];
        $tablesToAlter = [];

        // Find tables to create (in entity schema but not in database)
        foreach ($entitySchema as $tableName => $table) {
            if (!isset($databaseSchema[$tableName])) {
                $tablesToCreate[] = $table;
            }
        }

        // Find tables to drop (in database but not in entity schema)
        foreach ($databaseSchema as $tableName => $table) {
            if (!isset($entitySchema[$tableName])) {
                $tablesToDrop[] = $table;
            }
        }

        // Find tables to alter (exist in both but have differences)
        foreach ($entitySchema as $tableName => $entityTable) {
            if (isset($databaseSchema[$tableName])) {
                $databaseTable = $databaseSchema[$tableName];
                $tableDiff = $this->calculateTableDiff($entityTable, $databaseTable);

                if (!$tableDiff->isEmpty()) {
                    $tablesToAlter[$tableName] = $tableDiff;
                }
            }
        }

        return new SchemaDiff(
            tablesToCreate: $tablesToCreate,
            tablesToDrop: $tablesToDrop,
            tablesToAlter: $tablesToAlter,
        );
    }

    private function calculateTableDiff(
        Table $entityTable,
        Table $databaseTable,
    ): TableDiff {
        return new TableDiff(
            tableName: $entityTable->name,
            columnsToAdd: $this->findColumnsToAdd($entityTable->columns, $databaseTable->columns),
            columnsToDrop: $this->findColumnsToDrop($entityTable->columns, $databaseTable->columns),
            columnsToModify: $this->findColumnsToModify(
                $entityTable->columns,
                $databaseTable->columns,
                $entityTable->indexes
            ),
            indexesToAdd: $this->findIndexesToAdd($entityTable->indexes, $databaseTable->indexes),
            indexesToDrop: $this->findIndexesToDrop(
                $entityTable->indexes,
                $databaseTable->indexes,
                $entityTable->columns
            ),
            foreignKeysToAdd: $this->findForeignKeysToAdd($entityTable->foreignKeys, $databaseTable->foreignKeys),
            foreignKeysToDrop: $this->findForeignKeysToDrop($entityTable->foreignKeys, $databaseTable->foreignKeys),
        );
    }

    /**
     * Find columns that need to be added.
     *
     * @param array<Column> $entityColumns
     * @param array<Column> $databaseColumns
     * @return array<Column>
     */
    private function findColumnsToAdd(
        array $entityColumns,
        array $databaseColumns,
    ): array {
        $databaseColumnNames = $this->getColumnNames($databaseColumns);
        $columnsToAdd = [];

        foreach ($entityColumns as $column) {
            if (!in_array($column->name, $databaseColumnNames, true)) {
                $columnsToAdd[] = $column;
            }
        }

        return $columnsToAdd;
    }

    /**
     * Find columns that need to be dropped.
     *
     * @param array<Column> $entityColumns
     * @param array<Column> $databaseColumns
     * @return array<Column>
     */
    private function findColumnsToDrop(
        array $entityColumns,
        array $databaseColumns,
    ): array {
        $entityColumnNames = $this->getColumnNames($entityColumns);
        $columnsToDrop = [];

        foreach ($databaseColumns as $column) {
            if (!in_array($column->name, $entityColumnNames, true)) {
                $columnsToDrop[] = $column;
            }
        }

        return $columnsToDrop;
    }

    /**
     * Find columns that need to be modified.
     *
     * @param array<Column> $entityColumns
     * @param array<Column> $databaseColumns
     * @param array<Index> $entityIndexes
     * @return array<string, Column>
     */
    private function findColumnsToModify(
        array $entityColumns,
        array $databaseColumns,
        array $entityIndexes = [],
    ): array {
        $databaseColumnsIndexed = $this->indexColumnsByName($databaseColumns);
        $columnsToModify = [];

        // Find columns that have single-column unique indexes in the entity
        // These columns are effectively unique even if unique=false on the column
        $columnsWithUniqueIndex = [];
        foreach ($entityIndexes as $index) {
            if ($index->type === IndexType::Unique && count($index->columns) === 1) {
                $columnsWithUniqueIndex[] = $index->columns[0];
            }
        }

        foreach ($entityColumns as $entityColumn) {
            if (isset($databaseColumnsIndexed[$entityColumn->name])) {
                $databaseColumn = $databaseColumnsIndexed[$entityColumn->name];

                if (!$this->columnsEqual($entityColumn, $databaseColumn, $columnsWithUniqueIndex)) {
                    $columnsToModify[$entityColumn->name] = $entityColumn;
                }
            }
        }

        return $columnsToModify;
    }

    /**
     * Compare two columns for equality, with special handling for unique indexes.
     *
     * @param array<string> $columnsWithUniqueIndex Columns that have unique indexes defined
     */
    private function columnsEqual(
        Column $entityColumn,
        Column $databaseColumn,
        array $columnsWithUniqueIndex,
    ): bool {
        // If column equals() says they're equal, they're equal
        if ($entityColumn->equals($databaseColumn)) {
            return true;
        }

        // Check if the only difference is the unique flag
        // If entity column has unique=false but has a unique index on it,
        // and database column has unique=true, they're effectively equivalent
        if (
            !$entityColumn->unique
            && $databaseColumn->unique
            && in_array($entityColumn->name, $columnsWithUniqueIndex, true)
        ) {
            // Create a temporary column with unique=true to compare other properties
            $entityColumnWithUnique = new Column(
                name: $entityColumn->name,
                type: $entityColumn->type,
                length: $entityColumn->length,
                nullable: $entityColumn->nullable,
                default: $entityColumn->default,
                unique: true,
                primaryKey: $entityColumn->primaryKey,
                autoIncrement: $entityColumn->autoIncrement,
                references: $entityColumn->references,
                onDelete: $entityColumn->onDelete,
                onUpdate: $entityColumn->onUpdate,
            );

            return $entityColumnWithUnique->equals($databaseColumn);
        }

        return false;
    }

    /**
     * Find indexes that need to be added.
     *
     * @param array<Index> $entityIndexes
     * @param array<Index> $databaseIndexes
     * @return array<Index>
     */
    private function findIndexesToAdd(
        array $entityIndexes,
        array $databaseIndexes,
    ): array {
        $databaseIndexNames = $this->getIndexNames($databaseIndexes);
        $indexesToAdd = [];

        foreach ($entityIndexes as $index) {
            if (!in_array($index->name, $databaseIndexNames, true)) {
                $indexesToAdd[] = $index;
            }
        }

        return $indexesToAdd;
    }

    /**
     * Find indexes that need to be dropped.
     *
     * @param array<Index> $entityIndexes
     * @param array<Index> $databaseIndexes
     * @param array<Column> $entityColumns Entity columns (to check unique and FK properties)
     * @return array<Index>
     */
    private function findIndexesToDrop(
        array $entityIndexes,
        array $databaseIndexes,
        array $entityColumns = [],
    ): array {
        $entityIndexNames = $this->getIndexNames($entityIndexes);
        $indexesToDrop = [];

        // Get columns that have unique=true (uniqueness is column-based, not index-based)
        $uniqueEntityColumns = [];
        // Get columns that have foreign key references (MySQL auto-creates indexes for FKs)
        $fkEntityColumns = [];
        foreach ($entityColumns as $col) {
            if ($col->unique) {
                $uniqueEntityColumns[] = $col->name;
            }
            if ($col->references !== null) {
                $fkEntityColumns[] = $col->name;
            }
        }

        foreach ($databaseIndexes as $index) {
            if (in_array($index->name, $entityIndexNames, true)) {
                continue;  // Index exists in entity, don't drop
            }

            // Don't drop unique indexes that correspond to columns with unique=true
            // MySQL creates indexes for UNIQUE constraints, and if the entity defines
            // the column as unique, the index should be kept
            if (
                $index->type === IndexType::Unique
                && count($index->columns) === 1
                && in_array($index->columns[0], $uniqueEntityColumns, true)
            ) {
                continue;
            }

            // Don't drop indexes on foreign key columns
            // MySQL requires indexes on FK columns and auto-creates them
            if (
                count($index->columns) === 1
                && in_array($index->columns[0], $fkEntityColumns, true)
            ) {
                continue;
            }

            $indexesToDrop[] = $index;
        }

        return $indexesToDrop;
    }

    /**
     * Find foreign keys that need to be added.
     *
     * Matching is done by columns and referenced table, not by name, since
     * MySQL auto-generates FK names like "table_ibfk_1" while entities define
     * meaningful names like "fk_table_column".
     *
     * @param array<ForeignKey> $entityForeignKeys
     * @param array<ForeignKey> $databaseForeignKeys
     * @return array<ForeignKey>
     */
    private function findForeignKeysToAdd(
        array $entityForeignKeys,
        array $databaseForeignKeys,
    ): array {
        $foreignKeysToAdd = [];

        foreach ($entityForeignKeys as $entityFk) {
            $found = false;

            foreach ($databaseForeignKeys as $dbFk) {
                if ($this->foreignKeysMatch($entityFk, $dbFk)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $foreignKeysToAdd[] = $entityFk;
            }
        }

        return $foreignKeysToAdd;
    }

    /**
     * Find foreign keys that need to be dropped.
     *
     * Matching is done by columns and referenced table, not by name.
     *
     * @param array<ForeignKey> $entityForeignKeys
     * @param array<ForeignKey> $databaseForeignKeys
     * @return array<ForeignKey>
     */
    private function findForeignKeysToDrop(
        array $entityForeignKeys,
        array $databaseForeignKeys,
    ): array {
        $foreignKeysToDrop = [];

        foreach ($databaseForeignKeys as $dbFk) {
            $found = false;

            foreach ($entityForeignKeys as $entityFk) {
                if ($this->foreignKeysMatch($entityFk, $dbFk)) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $foreignKeysToDrop[] = $dbFk;
            }
        }

        return $foreignKeysToDrop;
    }

    /**
     * Check if two foreign keys match (ignoring name differences).
     *
     * Foreign keys match if they have the same columns, referenced table,
     * and referenced columns.
     */
    private function foreignKeysMatch(
        ForeignKey $fk1,
        ForeignKey $fk2,
    ): bool {
        return $fk1->columns === $fk2->columns
            && $fk1->referencedTable === $fk2->referencedTable
            && $fk1->referencedColumns === $fk2->referencedColumns;
    }

    /**
     * @param array<Column> $columns
     * @return array<string>
     */
    private function getColumnNames(
        array $columns,
    ): array {
        return array_map(
            static fn (Column $column): string => $column->name,
            $columns,
        );
    }

    /**
     * @param array<Column> $columns
     * @return array<string, Column>
     */
    private function indexColumnsByName(
        array $columns,
    ): array {
        $indexed = [];

        foreach ($columns as $column) {
            $indexed[$column->name] = $column;
        }

        return $indexed;
    }

    /**
     * @param array<Index> $indexes
     * @return array<string>
     */
    private function getIndexNames(
        array $indexes,
    ): array {
        return array_map(
            static fn (Index $index): string => $index->name,
            $indexes,
        );
    }

    /**
     * @param array<ForeignKey> $foreignKeys
     * @return array<string>
     */
    private function getForeignKeyNames(
        array $foreignKeys,
    ): array {
        return array_map(
            static fn (ForeignKey $foreignKey): string => $foreignKey->name,
            $foreignKeys,
        );
    }
}
