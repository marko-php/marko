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
     */
    public function __construct(
        public string $name,
        public array $columns,
        public bool $unique = false,
    ) {}
}
