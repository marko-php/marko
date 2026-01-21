<?php

declare(strict_types=1);

namespace Marko\Database\Diff;

use Marko\Database\Schema\Table;

readonly class SchemaDiff
{
    /**
     * @param array<Table> $tablesToCreate
     * @param array<Table> $tablesToDrop
     * @param array<string, TableDiff> $tablesToAlter
     */
    public function __construct(
        public array $tablesToCreate = [],
        public array $tablesToDrop = [],
        public array $tablesToAlter = [],
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->tablesToCreate)
            && empty($this->tablesToDrop)
            && empty($this->tablesToAlter);
    }

    public function hasDestructiveChanges(): bool
    {
        if (!empty($this->tablesToDrop)) {
            return true;
        }

        return array_any($this->tablesToAlter, fn ($tableDiff) => $tableDiff->hasDestructiveChanges());
    }

    /**
     * @return array<string>
     */
    public function getDestructiveChanges(): array
    {
        $changes = [];

        foreach ($this->tablesToDrop as $table) {
            $changes[] = "DROP TABLE $table->name";
        }

        foreach ($this->tablesToAlter as $tableDiff) {
            $changes = [...$changes, ...$tableDiff->getDestructiveChanges()];
        }

        return $changes;
    }

    public function getSummary(): string
    {
        $lines = [];

        foreach ($this->tablesToCreate as $table) {
            $lines[] = "Create table: $table->name";
        }

        foreach ($this->tablesToDrop as $table) {
            $lines[] = "Drop table: $table->name";
        }

        foreach ($this->tablesToAlter as $tableName => $tableDiff) {
            $lines[] = "Alter table: $tableName";
            $lines = [...$lines, ...$tableDiff->getSummaryLines()];
        }

        return implode("\n", $lines);
    }
}
