<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Dto\PaginatedResult;

class PaginationService implements PaginationServiceInterface
{
    private const int ADJACENT_PAGES = 1;

    public function __construct(
        private readonly BlogConfigInterface $config,
    ) {}

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
    ): PaginatedResult {
        $perPage = $perPage ?? $this->getPerPage();
        $totalPages = $totalItems > 0 ? (int) ceil($totalItems / $perPage) : 0;
        $hasPreviousPage = $currentPage > 1;
        $hasNextPage = $currentPage < $totalPages;

        return new PaginatedResult(
            items: $items,
            currentPage: $currentPage,
            totalItems: $totalItems,
            perPage: $perPage,
            totalPages: $totalPages,
            hasPreviousPage: $hasPreviousPage,
            hasNextPage: $hasNextPage,
            pageNumbers: $this->generatePageNumbers(
                currentPage: $currentPage,
                totalPages: $totalPages,
            ),
        );
    }

    public function calculateOffset(
        int $page,
        ?int $perPage = null,
    ): int {
        $perPage = $perPage ?? $this->getPerPage();

        return ($page - 1) * $perPage;
    }

    public function getPerPage(): int
    {
        return $this->config->getPostsPerPage();
    }

    /**
     * @return array<int|null>
     */
    private function generatePageNumbers(
        int $currentPage,
        int $totalPages,
    ): array {
        if ($totalPages === 0) {
            return [];
        }

        if ($totalPages <= 5) {
            return range(1, $totalPages);
        }

        $pages = [];

        // Always include first page
        $pages[] = 1;

        // Calculate range around current page
        $rangeStart = max(2, $currentPage - self::ADJACENT_PAGES);
        $rangeEnd = min($totalPages - 1, $currentPage + self::ADJACENT_PAGES);

        // Add ellipsis if there's a gap after first page
        if ($rangeStart > 2) {
            $pages[] = null;
        }

        // Add pages in range
        for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
            $pages[] = $i;
        }

        // Add ellipsis if there's a gap before last page
        if ($rangeEnd < $totalPages - 1) {
            $pages[] = null;
        }

        // Always include last page
        $pages[] = $totalPages;

        return $pages;
    }
}
