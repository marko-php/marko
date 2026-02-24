<?php

declare(strict_types=1);

namespace Marko\Pagination;

use Marko\Pagination\Contracts\PaginatorInterface;
use Marko\Pagination\Exceptions\PaginationException;

class OffsetPaginator implements PaginatorInterface
{
    private readonly int $lastPage;

    /**
     * @param array<mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
    ) {
        if ($currentPage < 1) {
            throw PaginationException::invalidPage($currentPage);
        }

        if ($perPage < 1) {
            throw PaginationException::invalidPerPage($perPage);
        }

        $this->lastPage = $total > 0
            ? (int) ceil($total / $perPage)
            : 1;
    }

    /**
     * @return array<mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function previousPage(): ?int
    {
        if ($this->currentPage <= 1) {
            return null;
        }

        return $this->currentPage - 1;
    }

    public function nextPage(): ?int
    {
        if (!$this->hasMorePages()) {
            return null;
        }

        return $this->currentPage + 1;
    }

    /**
     * @return array{items: array<mixed>, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'meta' => [
                'total' => $this->total,
                'per_page' => $this->perPage,
                'current_page' => $this->currentPage,
                'last_page' => $this->lastPage,
            ],
            'links' => [
                'previous' => $this->previousPage(),
                'next' => $this->nextPage(),
            ],
        ];
    }
}
