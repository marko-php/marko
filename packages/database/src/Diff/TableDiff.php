<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Column;
use Marko\Database\Schema\ForeignKey;
use Marko\Database\Schema\Index;

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
    ) {}

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
