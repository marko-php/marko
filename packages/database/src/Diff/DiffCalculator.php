<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\IdentifierName;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\IndexType;
use Marko\Database\Schema\Table;

class DiffCalculator
{
    /**
     * @param list<string> $ignoredIndexes Project-wide index names or fnmatch patterns the diff never drops
     */
    public function __construct(
        private array $ignoredIndexes = [],
    ) {}

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

        // Tables in the database but not in entity schema are left alone.
        // Only entity-managed tables participate in the diff. Migration-only
        // tables (sessions, etc.) have no entity and must not be dropped.

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
        $columnsToModify = $this->findColumnsToModify($entityTable->columns, $databaseTable->columns);
        $columnsToAdd = $this->findColumnsToAdd($entityTable->columns, $databaseTable->columns);
        $databaseColumns = $this->indexColumnsByName($databaseTable->columns);
        $uniqueIndexes = $this->deriveUniqueColumnIndexes($entityTable, $columnsToAdd);
        $indexesToAdd = $this->findIndexesToAdd($entityTable->indexes, $uniqueIndexes, $databaseTable->indexes);
        $indexesToDrop = $this->findIndexesToDrop($entityTable, $databaseTable->indexes);

        return new TableDiff(
            tableName: $entityTable->name,
            columnsToAdd: $columnsToAdd,
            columnsToDrop: $this->findColumnsToDrop($entityTable->columns, $databaseTable->columns),
            columnsToModify: $columnsToModify,
            indexesToAdd: [
                ...$indexesToAdd,
                ...$this->foreignKeyReplacementIndexes(
                    $entityTable,
                    $databaseTable->indexes,
                    $indexesToAdd,
                    $indexesToDrop,
                ),
            ],
            indexesToDrop: $indexesToDrop,
            foreignKeysToAdd: $this->findForeignKeysToAdd($entityTable->foreignKeys, $databaseTable->foreignKeys),
            foreignKeysToDrop: $this->findForeignKeysToDrop($entityTable->foreignKeys, $databaseTable->foreignKeys),
            columnsToModifyFrom: array_intersect_key($databaseColumns, $columnsToModify),
            currentPrimaryKey: array_values($this->getColumnNames(
                array_filter($databaseTable->columns, static fn (Column $column): bool => $column->primaryKey),
            )),
            currentTable: $databaseTable,
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
     * Uniqueness is not compared here: the index diff owns it (see deriveUniqueColumnIndexes()), so a column whose
     * only difference is its unique flag is not modified.
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
            $databaseColumn = $databaseColumnsIndexed[$entityColumn->name] ?? null;

            if ($databaseColumn === null) {
                continue;
            }

            if (!$entityColumn->withoutUnique()->equals($databaseColumn->withoutUnique())) {
                $columnsToModify[$entityColumn->name] = $entityColumn;
            }
        }

        return $columnsToModify;
    }

    /**
     * The unique index each existing `unique: true` column (not a primary key) implies, named
     * `<table>_<column>_unique` (shortened to 63 bytes by IdentifierName::derive()).
     *
     * A column the entity already covers with a declared single-column unique index gets none, and neither does a
     * column being added: its ADD COLUMN declares UNIQUE inline.
     *
     * @param array<Column> $columnsToAdd
     * @return array<Index>
     */
    private function deriveUniqueColumnIndexes(
        Table $entityTable,
        array $columnsToAdd,
    ): array {
        $addedColumnNames = $this->getColumnNames($columnsToAdd);
        $indexes = [];

        foreach ($entityTable->columns as $column) {
            if (
                !$column->unique
                || $column->primaryKey
                || in_array($column->name, $addedColumnNames, true)
                || array_any(
                    $entityTable->indexes,
                    fn (Index $index): bool => $this->isFullUniqueIndexOn($index, $column->name),
                )
            ) {
                continue;
            }

            $indexes[] = new Index(
                name: IdentifierName::derive("{$entityTable->name}_$column->name", suffix: '_unique'),
                columns: [$column->name],
                type: IndexType::Unique,
            );
        }

        return $indexes;
    }

    /**
     * Whether an index makes exactly one column unique on every row: unique, single-column and not partial.
     */
    private function isFullUniqueIndexOn(
        Index $index,
        string $columnName,
    ): bool {
        return $index->type === IndexType::Unique
            && $index->columns === [$columnName]
            && $index->where === null;
    }

    /**
     * Find indexes that need to be added.
     *
     * Declared indexes are matched by name. An index derived from a unique column is matched by its column instead,
     * so the unique index that CREATE TABLE or ADD COLUMN made inline (named `email` by MySQL, `users_email_key` by
     * PostgreSQL) satisfies it without being renamed.
     *
     * @param array<Index> $entityIndexes
     * @param array<Index> $uniqueColumnIndexes
     * @param array<Index> $databaseIndexes
     * @return array<Index>
     */
    private function findIndexesToAdd(
        array $entityIndexes,
        array $uniqueColumnIndexes,
        array $databaseIndexes,
    ): array {
        $databaseIndexNames = $this->getIndexNames($databaseIndexes);
        $indexesToAdd = [];

        foreach ($entityIndexes as $index) {
            if (!in_array($index->name, $databaseIndexNames, true)) {
                $indexesToAdd[] = $index;
            }
        }

        foreach ($uniqueColumnIndexes as $index) {
            $columnName = $index->columns[0];

            if (!array_any(
                $databaseIndexes,
                fn (Index $databaseIndex): bool => $this->isFullUniqueIndexOn($databaseIndex, $columnName),
            )) {
                $indexesToAdd[] = $index;
            }
        }

        return $indexesToAdd;
    }

    /**
     * Find indexes that need to be dropped.
     *
     * A database index stays when the entity declares it by name, when it is the unique index of a column the entity
     * marks `unique: true`, when it is opted out of the diff, or when it is a non-unique single-column index on a
     * foreign key column (MySQL creates those for every foreign key).
     *
     * @param array<Index> $databaseIndexes
     * @return array<Index>
     */
    private function findIndexesToDrop(
        Table $entityTable,
        array $databaseIndexes,
    ): array {
        $entityIndexNames = $this->getIndexNames($entityTable->indexes);
        $uniqueEntityColumns = [];
        $fkEntityColumns = [];

        foreach ($entityTable->columns as $column) {
            if ($column->unique && !$column->primaryKey) {
                $uniqueEntityColumns[] = $column->name;
            }

            if ($column->references !== null) {
                $fkEntityColumns[] = $column->name;
            }
        }

        $indexesToDrop = [];

        foreach ($databaseIndexes as $index) {
            if (in_array($index->name, $entityIndexNames, true)) {
                continue;
            }

            if ($this->isIgnoredIndex($index->name, $entityTable->unmanagedIndexes)) {
                continue;
            }

            if (array_any(
                $uniqueEntityColumns,
                fn (string $columnName): bool => $this->isFullUniqueIndexOn($index, $columnName),
            )) {
                continue;
            }

            if (
                $index->type !== IndexType::Unique
                && count($index->columns) === 1
                && in_array($index->columns[0], $fkEntityColumns, true)
            ) {
                continue;
            }

            $indexesToDrop[] = $index;
        }

        return $indexesToDrop;
    }

    /**
     * A plain `<table>_<column>_index` for each foreign key column whose unique index is dropped when nothing else
     * would index it any more: MySQL refuses to drop the last index a foreign key uses. Once added, it is kept as
     * the foreign key column's index (see findIndexesToDrop()). Its name is shortened to 63 bytes like
     * every derived name.
     *
     * @param array<Index> $databaseIndexes
     * @param array<Index> $indexesToAdd
     * @param array<Index> $indexesToDrop
     * @return array<Index>
     */
    private function foreignKeyReplacementIndexes(
        Table $entityTable,
        array $databaseIndexes,
        array $indexesToAdd,
        array $indexesToDrop,
    ): array {
        $droppedNames = $this->getIndexNames($indexesToDrop);
        $remainingIndexes = [
            ...array_filter(
                $databaseIndexes,
                static fn (Index $index): bool => !in_array($index->name, $droppedNames, true),
            ),
            ...$indexesToAdd,
        ];
        $replacements = [];

        foreach ($indexesToDrop as $index) {
            if ($index->type !== IndexType::Unique || count($index->columns) !== 1) {
                continue;
            }

            $columnName = $index->columns[0];
            $isForeignKeyColumn = array_any(
                $entityTable->columns,
                static fn (Column $column): bool => $column->name === $columnName && $column->references !== null,
            );
            $stillIndexed = array_any(
                $remainingIndexes,
                static fn (Index $remaining): bool => ($remaining->columns[0] ?? null) === $columnName,
            );

            if ($isForeignKeyColumn && !$stillIndexed) {
                $replacement = new Index(
                    name: IdentifierName::derive("{$entityTable->name}_$columnName", suffix: '_index'),
                    columns: [$columnName],
                );
                $replacements[] = $replacement;
                $remainingIndexes[] = $replacement;
            }
        }

        return $replacements;
    }

    /**
     * Whether an index was opted out of the diff, per table or project-wide.
     *
     * @param list<string> $unmanagedIndexes
     */
    private function isIgnoredIndex(
        string $indexName,
        array $unmanagedIndexes,
    ): bool {
        return array_any(
            [...$unmanagedIndexes, ...$this->ignoredIndexes],
            static fn (string $pattern): bool => fnmatch($pattern, $indexName),
        );
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
            if (!array_any(
                $databaseForeignKeys,
                fn (ForeignKey $dbFk): bool => $this->foreignKeysMatch($entityFk, $dbFk),
            )) {
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
            if (!array_any(
                $entityForeignKeys,
                fn (ForeignKey $entityFk): bool => $this->foreignKeysMatch($entityFk, $dbFk),
            )) {
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
}
