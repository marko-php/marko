<?php

declare(strict_types=1);

namespace Marko\Search\Driver;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Search\Contracts\SearchableInterface;
use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;
use Marko\Search\Value\SearchResult;

readonly class DatabaseSearchDriver implements SearchInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $tableName,
        private SearchableInterface $searchable,
    ) {}

    public function search(
        string $query,
        SearchCriteria $criteria,
    ): SearchResult {
        $fields = array_keys($this->searchable->getSearchableFields());

        // Build LIKE conditions for text search
        $likeClauses = [];
        $searchBindings = [];

        foreach ($fields as $field) {
            $likeClauses[] = "$field LIKE ?";
            $searchBindings[] = "%$query%";
        }

        $whereClause = '(' . implode(' OR ', $likeClauses) . ')';
        $bindings = $searchBindings;

        // Apply filters from SearchCriteria
        $filterClauses = [];

        foreach ($criteria->filters as $filter) {
            [$clause, $filterBindings] = $this->buildFilterClause($filter);
            $filterClauses[] = $clause;
            $bindings = array_merge($bindings, $filterBindings);
        }

        if ($filterClauses !== []) {
            $whereClause .= ' AND ' . implode(' AND ', $filterClauses);
        }

        // Count query
        $countSql = "SELECT COUNT(*) as count FROM $this->tableName WHERE $whereClause";
        $countResult = $this->connection->query($countSql, $bindings);
        $total = (int) ($countResult[0]['count'] ?? 0);

        // Data query
        $sql = "SELECT * FROM $this->tableName WHERE $whereClause";

        // Apply sorting
        if ($criteria->sortBy !== '') {
            $sql .= " ORDER BY $criteria->sortBy $criteria->sortDirection";
        }

        // Apply pagination
        $offset = ($criteria->page - 1) * $criteria->perPage;
        $sql .= " LIMIT $criteria->perPage OFFSET $offset";

        $rows = $this->connection->query($sql, $bindings);

        return new SearchResult(
            items: $rows,
            total: $total,
            query: $query,
            page: $criteria->page,
            perPage: $criteria->perPage,
        );
    }

    /**
     * Build a SQL clause and bindings for a search filter.
     *
     * @return array{string, array<mixed>}
     */
    private function buildFilterClause(
        SearchFilter $filter,
    ): array {
        return match ($filter->operator) {
            FilterOperator::Equals => ["$filter->field = ?", [$filter->value]],
            FilterOperator::NotEquals => ["$filter->field != ?", [$filter->value]],
            FilterOperator::GreaterThan => ["$filter->field > ?", [$filter->value]],
            FilterOperator::LessThan => ["$filter->field < ?", [$filter->value]],
            FilterOperator::Like => ["$filter->field LIKE ?", [$filter->value]],
            FilterOperator::In => [
                "$filter->field IN (" . implode(', ', array_fill(0, count((array) $filter->value), '?')) . ')',
                (array) $filter->value,
            ],
        };
    }
}
