<?php

declare(strict_types=1);

namespace Marko\Pagination\Contracts;

interface CursorPaginatorInterface
{
    /**
     * Get the items for the current page.
     *
     * @return array<mixed>
     */
    public function items(): array;

    /**
     * Get the number of items per page.
     */
    public function perPage(): int;

    /**
     * Determine if there are more pages after the current cursor position.
     */
    public function hasMorePages(): bool;

    /**
     * Get the current cursor, or null if on the first page.
     */
    public function cursor(): ?CursorInterface;

    /**
     * Get the next cursor, or null if on the last page.
     */
    public function nextCursor(): ?CursorInterface;

    /**
     * Get the previous cursor, or null if on the first page.
     */
    public function previousCursor(): ?CursorInterface;

    /**
     * Serialize the paginator to an array for API responses.
     *
     * @return array{items: array<mixed>, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public function toArray(): array;
}
