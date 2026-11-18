<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class Index
{
    /**
     * @param array<string> $columns
     * @param string|null $where SQL predicate that makes this a partial index (PostgreSQL only)
     * @param string|null $name Used literally when given. Without it Marko derives `<table>_<columns>_index`,
     *                          or `<table>_<columns>_unique` for a unique index
     */
    public function __construct(
        public array $columns,
        public bool $unique = false,
        public ?string $where = null,
        public ?string $name = null,
    ) {}
}
