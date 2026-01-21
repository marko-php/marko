<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class ForeignKey
{
    /**
     * @param array<string> $columns
     * @param array<string> $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {}

    public function equals(
        self $other,
    ): bool {
        return $this->name === $other->name
            && $this->columns === $other->columns
            && $this->referencedTable === $other->referencedTable
            && $this->referencedColumns === $other->referencedColumns
            && $this->onDelete === $other->onDelete
            && $this->onUpdate === $other->onUpdate;
    }
}
