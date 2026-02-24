<?php

declare(strict_types=1);

namespace Marko\Pagination\Contracts;

interface PaginatorInterface
{
    /**
     * Get the items for the current page.
     *
     * @return array<mixed>
     */
    public function items(): array;

    /**
     * Get the total number of items across all pages.
     */
    public function total(): int;

    /**
     * Get the number of items per page.
     */
    public function perPage(): int;

    /**
     * Get the current page number.
     */
    public function currentPage(): int;

    /**
     * Get the last page number.
     */
    public function lastPage(): int;

    /**
     * Determine if there are more pages after the current page.
     */
    public function hasMorePages(): bool;

    /**
     * Get the previous page number, or null if on the first page.
     */
    public function previousPage(): ?int;

    /**
     * Get the next page number, or null if on the last page.
     */
    public function nextPage(): ?int;

    /**
     * Serialize the paginator to an array for API responses.
     *
     * @return array{items: array<mixed>, meta: array<string, mixed>, links: array<string, mixed>}
     */
    public function toArray(): array;
}
