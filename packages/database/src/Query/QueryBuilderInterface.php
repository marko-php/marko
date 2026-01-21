<?php

declare(strict_types=1);

namespace Marko\Database\Query;

/**
 * Interface for building SQL queries with a fluent API.
 */
interface QueryBuilderInterface
{
    /**
     * Set the target table for the query.
     *
     * @param string $table The table name
     * @return static For fluent chaining
     */
    public function table(string $table): static;

    /**
     * Set the columns to select.
     *
     * @param string ...$columns Column names to select
     * @return static For fluent chaining
     */
    public function select(string ...$columns): static;

    /**
     * Add a WHERE condition.
     *
     * @param string $column The column name
     * @param string $operator The comparison operator
     * @param mixed $value The value to compare against
     * @return static For fluent chaining
     */
    public function where(
        string $column,
        string $operator,
        mixed $value,
    ): static;

    /**
     * Add a WHERE IN condition.
     *
     * @param string $column The column name
     * @param array<mixed> $values The values to match against
     * @return static For fluent chaining
     */
    public function whereIn(
        string $column,
        array $values,
    ): static;

    /**
     * Add a WHERE IS NULL condition.
     *
     * @param string $column The column name
     * @return static For fluent chaining
     */
    public function whereNull(string $column): static;

    /**
     * Add a WHERE IS NOT NULL condition.
     *
     * @param string $column The column name
     * @return static For fluent chaining
     */
    public function whereNotNull(string $column): static;

    /**
     * Add an OR WHERE condition.
     *
     * @param string $column The column name
     * @param string $operator The comparison operator
     * @param mixed $value The value to compare against
     * @return static For fluent chaining
     */
    public function orWhere(
        string $column,
        string $operator,
        mixed $value,
    ): static;

    /**
     * Add an INNER JOIN clause.
     *
     * @param string $table The table to join
     * @param string $first The first column
     * @param string $operator The comparison operator
     * @param string $second The second column
     * @return static For fluent chaining
     */
    public function join(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static;

    /**
     * Add a LEFT JOIN clause.
     *
     * @param string $table The table to join
     * @param string $first The first column
     * @param string $operator The comparison operator
     * @param string $second The second column
     * @return static For fluent chaining
     */
    public function leftJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static;

    /**
     * Add a RIGHT JOIN clause.
     *
     * @param string $table The table to join
     * @param string $first The first column
     * @param string $operator The comparison operator
     * @param string $second The second column
     * @return static For fluent chaining
     */
    public function rightJoin(
        string $table,
        string $first,
        string $operator,
        string $second,
    ): static;

    /**
     * Add an ORDER BY clause.
     *
     * @param string $column The column to order by
     * @param string $direction The sort direction (ASC or DESC)
     * @return static For fluent chaining
     */
    public function orderBy(
        string $column,
        string $direction = 'ASC',
    ): static;

    /**
     * Set the maximum number of rows to return.
     *
     * @param int $limit The limit
     * @return static For fluent chaining
     */
    public function limit(int $limit): static;

    /**
     * Set the number of rows to skip.
     *
     * @param int $offset The offset
     * @return static For fluent chaining
     */
    public function offset(int $offset): static;

    /**
     * Execute the query and return all rows.
     *
     * @return array<array<string, mixed>> The result rows
     */
    public function get(): array;

    /**
     * Execute the query and return the first row or null.
     *
     * @return array<string, mixed>|null The first row or null
     */
    public function first(): ?array;

    /**
     * Insert a new row into the table.
     *
     * @param array<string, mixed> $data Column-value pairs to insert
     * @return int The last insert ID
     */
    public function insert(array $data): int;

    /**
     * Update rows in the table.
     *
     * @param array<string, mixed> $data Column-value pairs to update
     * @return int Number of affected rows
     */
    public function update(array $data): int;

    /**
     * Delete rows from the table.
     *
     * @return int Number of affected rows
     */
    public function delete(): int;

    /**
     * Get the count of matching rows.
     *
     * @return int The count
     */
    public function count(): int;

    /**
     * Execute a raw SQL query with optional bindings.
     *
     * @param string $sql The raw SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @return array<array<string, mixed>> The result rows
     */
    public function raw(
        string $sql,
        array $bindings = [],
    ): array;
}
