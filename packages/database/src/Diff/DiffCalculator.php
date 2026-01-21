<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
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
            columnsToModify: $this->findColumnsToModify($entityTable->columns, $databaseTable->columns),
            indexesToAdd: $this->findIndexesToAdd($entityTable->indexes, $databaseTable->indexes),
            indexesToDrop: $this->findIndexesToDrop($entityTable->indexes, $databaseTable->indexes),
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
     * @return array<string, Column>
     */
    private function findColumnsToModify(
        array $entityColumns,
        array $databaseColumns,
    ): array {
        $databaseColumnsIndexed = $this->indexColumnsByName($databaseColumns);
        $columnsToModify = [];

        foreach ($entityColumns as $entityColumn) {
            if (isset($databaseColumnsIndexed[$entityColumn->name])) {
                $databaseColumn = $databaseColumnsIndexed[$entityColumn->name];

                if (!$entityColumn->equals($databaseColumn)) {
                    $columnsToModify[$entityColumn->name] = $entityColumn;
                }
            }
        }

        return $columnsToModify;
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
     * @return array<Index>
     */
    private function findIndexesToDrop(
        array $entityIndexes,
        array $databaseIndexes,
    ): array {
        $entityIndexNames = $this->getIndexNames($entityIndexes);
        $indexesToDrop = [];

        foreach ($databaseIndexes as $index) {
            if (!in_array($index->name, $entityIndexNames, true)) {
                $indexesToDrop[] = $index;
            }
        }

        return $indexesToDrop;
    }

    /**
     * Find foreign keys that need to be added.
     *
     * @param array<ForeignKey> $entityForeignKeys
     * @param array<ForeignKey> $databaseForeignKeys
     * @return array<ForeignKey>
     */
    private function findForeignKeysToAdd(
        array $entityForeignKeys,
        array $databaseForeignKeys,
    ): array {
        $databaseForeignKeyNames = $this->getForeignKeyNames($databaseForeignKeys);
        $foreignKeysToAdd = [];

        foreach ($entityForeignKeys as $foreignKey) {
            if (!in_array($foreignKey->name, $databaseForeignKeyNames, true)) {
                $foreignKeysToAdd[] = $foreignKey;
            }
        }

        return $foreignKeysToAdd;
    }

    /**
     * Find foreign keys that need to be dropped.
     *
     * @param array<ForeignKey> $entityForeignKeys
     * @param array<ForeignKey> $databaseForeignKeys
     * @return array<ForeignKey>
     */
    private function findForeignKeysToDrop(
        array $entityForeignKeys,
        array $databaseForeignKeys,
    ): array {
        $entityForeignKeyNames = $this->getForeignKeyNames($entityForeignKeys);
        $foreignKeysToDrop = [];

        foreach ($databaseForeignKeys as $foreignKey) {
            if (!in_array($foreignKey->name, $entityForeignKeyNames, true)) {
                $foreignKeysToDrop[] = $foreignKey;
            }
        }

        return $foreignKeysToDrop;
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
