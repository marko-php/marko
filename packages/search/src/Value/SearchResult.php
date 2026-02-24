<?php

declare(strict_types=1);

namespace Marko\Search\Value;

readonly class SearchResult
{
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public string $query,
        public int $page,
        public int $perPage,
    ) {}

    public function totalPages(): int
    {
        if ($this->perPage <= 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->perPage);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
