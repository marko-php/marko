<?php

declare(strict_types=1);

namespace Marko\Search\Value;

readonly class SearchCriteria
{
    /**
     * @param array<SearchFilter> $filters
     */
    public function __construct(
        public string $query = '',
        public array $filters = [],
        public string $sortBy = '',
        public string $sortDirection = 'asc',
        public int $page = 1,
        public int $perPage = 15,
    ) {}

    public static function create(
        string $query = '',
    ): static {
        return new static(query: $query);
    }

    public function withFilter(
        SearchFilter $filter,
    ): static {
        $filters = $this->filters;
        $filters[] = $filter;

        return new static(
            query: $this->query,
            filters: $filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            page: $this->page,
            perPage: $this->perPage,
        );
    }

    public function withSort(
        string $field,
        string $direction = 'asc',
    ): static {
        return new static(
            query: $this->query,
            filters: $this->filters,
            sortBy: $field,
            sortDirection: $direction,
            page: $this->page,
            perPage: $this->perPage,
        );
    }

    public function withPage(
        int $page,
    ): static {
        return new static(
            query: $this->query,
            filters: $this->filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            page: $page,
            perPage: $this->perPage,
        );
    }

    public function withPerPage(
        int $perPage,
    ): static {
        return new static(
            query: $this->query,
            filters: $this->filters,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
            page: $this->page,
            perPage: $perPage,
        );
    }
}
