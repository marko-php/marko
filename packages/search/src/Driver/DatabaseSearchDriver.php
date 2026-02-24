<?php

declare(strict_types=1);

namespace Marko\Search\Driver;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Search\Contracts\SearchableInterface;
use Marko\Search\Contracts\SearchInterface;
use Marko\Search\Exceptions\SearchException;
use Marko\Search\Value\FilterOperator;
use Marko\Search\Value\SearchCriteria;
use Marko\Search\Value\SearchFilter;
use Marko\Search\Value\SearchResult;

readonly class DatabaseSearchDriver implements SearchInterface
{
    private const string IDENTIFIER_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const array VALID_SORT_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        private ConnectionInterface $connection,
        private string $tableName,
        private SearchableInterface $searchable,
    ) {}

    /**
     * @throws SearchException
     */
    public function search(
        string $query,
        SearchCriteria $criteria,
    ): SearchResult {
        $this->assertValidIdentifier($this->tableName, 'table');
        $fields = array_keys($this->searchable->getSearchableFields());

        // Build LIKE conditions for text search
        $likeClauses = [];
        $searchBindings = [];

        foreach ($fields as $field) {
            $this->assertValidIdentifier($field, 'column');
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
            $this->assertValidIdentifier($criteria->sortBy, 'sort column');
            $this->assertValidSortDirection($criteria->sortDirection);
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
     * @return array{string, array}
     *
     * @throws SearchException
     */
    private function buildFilterClause(
        SearchFilter $filter,
    ): array {
        $this->assertValidIdentifier($filter->field, 'filter column');

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

    /**
     * Validate that an identifier contains only safe characters for SQL.
     *
     * @throws SearchException
     */
    private function assertValidIdentifier(
        string $identifier,
        string $type,
    ): void {
        if (!preg_match(self::IDENTIFIER_PATTERN, $identifier)) {
            throw SearchException::invalidIdentifier($identifier, $type);
        }
    }

    /**
     * Validate that a sort direction is either 'asc' or 'desc'.
     *
     * @throws SearchException
     */
    private function assertValidSortDirection(
        string $direction,
    ): void {
        if (!in_array(strtolower($direction), self::VALID_SORT_DIRECTIONS, true)) {
            throw SearchException::invalidSortDirection($direction);
        }
    }
}
