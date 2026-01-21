<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Index
{
    /**
     * @param array<string> $columns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public IndexType $type = IndexType::Btree,
    ) {}

    public function equals(
        self $other,
    ): bool {
        return $this->name === $other->name
            && $this->columns === $other->columns
            && $this->type === $other->type;
    }
}
