<?php

declare(strict_types=1);

namespace Marko\Pagination;

use Marko\Pagination\Contracts\CursorInterface;
use Marko\Pagination\Contracts\CursorPaginatorInterface;
use Marko\Pagination\Exceptions\PaginationException;

class CursorPaginator implements CursorPaginatorInterface
{
    /**
     * @param array<mixed> $items
     */
    public function __construct(
        private readonly array $items,
        private readonly int $perPage,
        private readonly ?CursorInterface $cursor = null,
        private readonly ?CursorInterface $nextCursor = null,
        private readonly ?CursorInterface $previousCursor = null,
    ) {
        if ($perPage < 1) {
            throw PaginationException::invalidPerPage($perPage);
        }
    }

    /**
     * @return array<mixed>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function hasMorePages(): bool
    {
        return $this->nextCursor !== null;
    }

    public function cursor(): ?CursorInterface
    {
        return $this->cursor;
    }

    public function nextCursor(): ?CursorInterface
    {
        return $this->nextCursor;
    }

    public function previousCursor(): ?CursorInterface
    {
        return $this->previousCursor;
    }

    /**
     * @return array{items: array<mixed>, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'meta' => [
                'per_page' => $this->perPage,
                'has_more' => $this->hasMorePages(),
            ],
            'links' => [
                'previous' => $this->previousCursor?->encode(),
                'next' => $this->nextCursor?->encode(),
            ],
        ];
    }
}
