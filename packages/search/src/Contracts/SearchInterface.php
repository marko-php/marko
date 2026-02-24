<?php

declare(strict_types=1);

namespace Marko\Search\Contracts;

use Marko\Search\Exceptions\SearchException;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchResult;

interface SearchInterface
{
    /**
     * Execute a search query with optional criteria.
     *
     * @throws SearchException
     */
    public function search(
        string $query,
        SearchCriteria $criteria,
    ): SearchResult;
}
