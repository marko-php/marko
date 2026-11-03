<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Exceptions\MigrationException;
use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;
use Marko\Database\Schema\Table;

readonly class TableDiff
{
    /**
     * @param array<Column> $columnsToAdd
     * @param array<Column> $columnsToDrop
     * @param array<string, Column> $columnsToModify
     * @param array<Index> $indexesToAdd
     * @param array<Index> $indexesToDrop
     * @param array<ForeignKey> $foreignKeysToAdd
     * @param array<ForeignKey> $foreignKeysToDrop
     * @param array<string, Column> $columnsToModifyFrom The database's current definition of each column in
     *                                                   $columnsToModify, keyed by the same column names
     * @param list<string> $currentPrimaryKey The columns of the table's current primary key in the database, in column
     *                                       order; empty when the table has none. Context for the SQL generators,
     *                                       not a change.
     * @param ?Table $currentTable The table as the database has it now: every column, index (including unmanaged
     *                             ones) and foreign key. Context for the SQL generators (a table rebuild
     *                             re-creates all of it), not a change.
     */
    public function __construct(
        public string $tableName,
        public array $columnsToAdd = [],
        public array $columnsToDrop = [],
        public array $columnsToModify = [],
        public array $indexesToAdd = [],
        public array $indexesToDrop = [],
        public array $foreignKeysToAdd = [],
        public array $foreignKeysToDrop = [],
        public array $columnsToModifyFrom = [],
        public array $currentPrimaryKey = [],
        public ?Table $currentTable = null,
    ) {}

    /**
     * The database's current definition of a column in $columnsToModify.
     *
     * @throws MigrationException When the diff holds no previous definition for the column
     */
    public function previousColumn(
        string $columnName,
    ): Column {
        return $this->columnsToModifyFrom[$columnName]
            ?? throw MigrationException::missingPreviousColumn($this->tableName, $columnName);
    }

    /**
     * Refuse the primary key changes no generated ALTER TABLE can make: adding key columns to a table that
     * already has a primary key (a table has one; the diff does not replace it, even when this diff drops the
     * current key's columns), and dropping only some columns of the current key (PostgreSQL would drop the whole
     * key, MySQL would shrink it).
     *
     * @throws MigrationException When the diff adds a key to a table that has one, or drops part of its key
     */
    public function assertSupportedPrimaryKeyChange(
        string $driver,
    ): void {
        $addedKeyColumns = array_values(array_map(
            static fn (Column $column): string => $column->name,
            array_filter($this->columnsToAdd, static fn (Column $column): bool => $column->primaryKey),
        ));

        if ($addedKeyColumns !== [] && $this->currentPrimaryKey !== []) {
            throw MigrationException::primaryKeyAlreadyExists(
                $this->tableName,
                $this->currentPrimaryKey,
                $addedKeyColumns,
            );
        }

        $droppedColumns = array_map(static fn (Column $column): string => $column->name, $this->columnsToDrop);
        $droppedKeyColumns = array_values(array_intersect($this->currentPrimaryKey, $droppedColumns));

        if ($droppedKeyColumns !== [] && $droppedKeyColumns !== $this->currentPrimaryKey) {
            throw MigrationException::columnChangeNotSupported(
                $this->tableName,
                $droppedKeyColumns[0],
                $driver,
                'primary key',
            );
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->columnsToAdd)
            && empty($this->columnsToDrop)
            && empty($this->columnsToModify)
            && empty($this->indexesToAdd)
            && empty($this->indexesToDrop)
            && empty($this->foreignKeysToAdd)
            && empty($this->foreignKeysToDrop);
    }

    public function hasDestructiveChanges(): bool
    {
        return !empty($this->columnsToDrop)
            || !empty($this->indexesToDrop)
            || !empty($this->foreignKeysToDrop);
    }

    /**
     * A diff holding only this table's drops.
     */
    public function destructiveOnly(): self
    {
        return new self(
            tableName: $this->tableName,
            columnsToDrop: $this->columnsToDrop,
            indexesToDrop: $this->indexesToDrop,
            foreignKeysToDrop: $this->foreignKeysToDrop,
            currentPrimaryKey: $this->currentPrimaryKey,
            currentTable: $this->currentTable,
        );
    }

    /**
     * @return array<string>
     */
    public function getDestructiveChanges(): array
    {
        $changes = [];

        foreach ($this->columnsToDrop as $column) {
            $changes[] = "DROP COLUMN $this->tableName.$column->name";
        }

        foreach ($this->indexesToDrop as $index) {
            $changes[] = "DROP INDEX $this->tableName.$index->name";
        }

        foreach ($this->foreignKeysToDrop as $foreignKey) {
            $changes[] = "DROP FOREIGN KEY $this->tableName.$foreignKey->name";
        }

        return $changes;
    }

    /**
     * @return array<string>
     */
    public function getSummaryLines(): array
    {
        $lines = [];

        foreach ($this->columnsToAdd as $column) {
            $lines[] = "  Add column: $column->name";
        }

        foreach ($this->columnsToDrop as $column) {
            $lines[] = "  Drop column: $column->name";
        }

        foreach ($this->columnsToModify as $column) {
            $lines[] = "  Modify column: $column->name";
        }

        foreach ($this->indexesToAdd as $index) {
            $lines[] = "  Add index: $index->name";
        }

        foreach ($this->indexesToDrop as $index) {
            $lines[] = "  Drop index: $index->name";
        }

        foreach ($this->foreignKeysToAdd as $foreignKey) {
            $lines[] = "  Add foreign key: $foreignKey->name";
        }

        foreach ($this->foreignKeysToDrop as $foreignKey) {
            $lines[] = "  Drop foreign key: $foreignKey->name";
        }

        return $lines;
    }
}
