<?php

declare(strict_types=1);

namespace Marko\Blog\Dto;

/**
 * @template T
 */
readonly class PaginatedResult
{
    /**
     * @param array<T> $items
     * @param array<int|null> $pageNumbers
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $totalItems,
        public int $perPage,
        public int $totalPages,
        public bool $hasPreviousPage,
        public bool $hasNextPage,
        public array $pageNumbers,
    ) {}

    public function getPreviousPage(): ?int
    {
        if (!$this->hasPreviousPage) {
            return null;
        }

        return $this->currentPage - 1;
    }

    public function getNextPage(): ?int
    {
        if (!$this->hasNextPage) {
            return null;
        }

        return $this->currentPage + 1;
    }

    public function shouldShowPagination(): bool
    {
        return $this->totalPages > 1;
    }

    public function isEmpty(): bool
    {
        return $this->totalItems === 0;
    }
}
