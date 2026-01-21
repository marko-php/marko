<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Table
{
    /**
     * @param array<Column> $columns
     * @param array<Index> $indexes
     * @param array<ForeignKey> $foreignKeys
     */
    public function __construct(
        public string $name,
        public array $columns = [],
        public array $indexes = [],
        public array $foreignKeys = [],
    ) {}

    public function withColumn(
        Column $column,
    ): self {
        return new self(
            name: $this->name,
            columns: [...$this->columns, $column],
            indexes: $this->indexes,
            foreignKeys: $this->foreignKeys,
        );
    }

    public function withIndex(
        Index $index,
    ): self {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: [...$this->indexes, $index],
            foreignKeys: $this->foreignKeys,
        );
    }

    public function withForeignKey(
        ForeignKey $foreignKey,
    ): self {
        return new self(
            name: $this->name,
            columns: $this->columns,
            indexes: $this->indexes,
            foreignKeys: [...$this->foreignKeys, $foreignKey],
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

        if (count($this->foreignKeys) !== count($other->foreignKeys)) {
            return false;
        }

        foreach ($this->foreignKeys as $i => $foreignKey) {
            if (!$foreignKey->equals($other->foreignKeys[$i])) {
                return false;
            }
        }

        return true;
    }
}
