<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column
{
    public function __construct(
        public ?string $name = null,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?int $length = null,
        public ?string $type = null,
        public bool $unique = false,
        public mixed $default = null,
        public ?string $references = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
        public ?bool $nullable = null,
    ) {}
}
