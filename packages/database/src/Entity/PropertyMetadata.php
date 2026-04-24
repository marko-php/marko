<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds metadata about a single entity property.
 */
readonly class PropertyMetadata
{
    public function __construct(
        public string $name,
        public string $columnName,
        public string $type,
        public bool $nullable = false,
        public bool $isPrimaryKey = false,
        public bool $isAutoIncrement = false,
        public ?string $enumClass = null,
        public mixed $default = null,
        public ?string $columnType = null,
    ) {}
}
