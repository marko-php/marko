<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Dto\PaginatedResult;

interface PaginationServiceInterface
{
    /**
     * @template T
     * @param array<T> $items
     * @return PaginatedResult<T>
     */
    public function paginate(
        array $items,
        int $totalItems,
        int $currentPage,
        ?int $perPage = null,
    ): PaginatedResult;

    public function calculateOffset(
        int $page,
        ?int $perPage = null,
    ): int;

    public function getPerPage(): int;
}
