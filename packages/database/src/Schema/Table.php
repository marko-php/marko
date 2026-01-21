<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Table
{
    /**
     * @param array<Column> $columns
     * @param array<Index> $indexes
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
    ) {}

    public function withColumn(
        Column $column,
    ): self {
        return new self(
            name: $this->name,
            columns: [...$this->columns, $column],
            indexes: $this->indexes,
        );
    }

    public function withIndex(
        Index $index,
    ): self {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: [...$this->indexes, $index],
        );
    }

    public function equals(
        self $other,
    ): bool {
        if ($this->name !== $other->name) {
            return false;
        }

        if (count($this->columns) !== count($other->columns)) {
            return false;
        }

        foreach ($this->columns as $i => $column) {
            if (!$column->equals($other->columns[$i])) {
                return false;
            }
        }

        if (count($this->indexes) !== count($other->indexes)) {
            return false;
        }

        foreach ($this->indexes as $i => $index) {
            if (!$index->equals($other->indexes[$i])) {
                return false;
            }
        }

        return true;
    }
}
