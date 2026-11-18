<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed index metadata from entity attributes.
 */
readonly class IndexMetadata
{
    /**
     * @param array<string> $columns
     * @param string|null $where SQL predicate that makes this a partial index (PostgreSQL only)
     * @param string|null $name The declared name, or null when SchemaBuilder derives it from the table
     */
    public function __construct(
        public array $columns,
        public bool $unique = false,
        public ?string $where = null,
        public ?string $name = null,
    ) {}
}
