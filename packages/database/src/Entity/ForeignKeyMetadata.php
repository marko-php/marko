<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed foreign key metadata from entity attributes.
 */
readonly class ForeignKeyMetadata
{
    /**
     * @param array<string> $columns Database column names on the owning table
     * @param array<string> $referencedColumns
     * @param string|null $name The declared name, or null when it is derived later
     */
    public function __construct(
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?string $name = null,
    ) {}
}
