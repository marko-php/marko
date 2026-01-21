<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class ForeignKey
{
    /**
     * @param array<string> $columns
     * @param array<string> $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $references,
        public array $referencedColumns,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {}
}
