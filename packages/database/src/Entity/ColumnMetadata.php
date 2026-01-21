<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed column metadata from entity attributes.
 */
readonly class ColumnMetadata
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $unique = false,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?string $references = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {}
}
