<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

use Marko\Blog\Dto\SearchResult;

interface SearchServiceInterface
{
    /**
     * Search for posts matching the given query.
     *
     * @param string $query The search query (may contain multiple terms)
     * @return array<SearchResult> Array of search results sorted by relevance (highest first)
     */
    public function search(
        string $query,
    ): array;

    /**
     * Search for posts with pagination support.
     *
     * @param string $query The search query
     * @param int $limit Maximum results to return
     * @param int $offset Number of results to skip
     * @return array{results: array<SearchResult>, total: int}
     */
    public function searchPaginated(
        string $query,
        int $limit,
        int $offset,
    ): array;
}
