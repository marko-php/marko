<?php

declare(strict_types=1);

namespace Marko\Database\Query;

use Marko\Database\Exceptions\InvalidColumnException;
use Marko\Database\Exceptions\InvalidJsonPathException;
use Marko\Database\Exceptions\UnionShapeMismatchException;

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
     * Enable DISTINCT selection to deduplicate result rows.
     *
     * Note: When combined with union(), DISTINCT is applied only to this side
     * of the union (the left query). UNION already deduplicates its combined
     * result, so distinct() before union() is redundant but harmless.
     * distinct() before unionAll() is meaningful: it deduplicates this side
     * before concatenating the other side raw.
     *
     * @return static For fluent chaining
     */
    public function distinct(): static;

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
     * @param array $values The values to match against
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
     * Add a WHERE JSON_CONTAINS condition (checks if a JSON column/path contains a value).
     *
     * @param string $path   Column name or JSON path expression (e.g. "tags" or "data->roles")
     * @param mixed  $value  The value to search for (will be JSON-encoded as a binding)
     * @return static For fluent chaining
     * @throws InvalidJsonPathException When the path expression is invalid
     */
    public function whereJsonContains(
        string $path,
        mixed $value,
    ): static;

    /**
     * Add a WHERE condition that checks a JSON path exists in a column.
     *
     * @param string $path JSON path expression (e.g. "data->middle_name")
     * @return static For fluent chaining
     * @throws InvalidJsonPathException When the path expression is invalid
     */
    public function whereJsonExists(
        string $path,
    ): static;

    /**
     * Add a WHERE condition that checks a JSON path does NOT exist in a column.
     *
     * @param string $path JSON path expression (e.g. "data->middle_name")
     * @return static For fluent chaining
     * @throws InvalidJsonPathException When the path expression is invalid
     */
    public function whereJsonMissing(
        string $path,
    ): static;

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
     * Add a GROUP BY clause.
     *
     * Column names are validated against the identifier whitelist (same rules as SELECT).
     *
     * @param string ...$columns Column names to group by
     * @return static For fluent chaining
     * @throws InvalidColumnException When a column name is invalid
     */
    public function groupBy(string ...$columns): static;

    /**
     * Add a HAVING clause with a raw expression and positional bindings.
     *
     * The expression string must not contain semicolons or SQL comments (-- or /*).
     * Only use ? placeholders for user-supplied values; never interpolate user input
     * directly into the expression string.
     *
     * @param string $expression The raw HAVING expression (e.g. "COUNT(*) > ?")
     * @param array $bindings Positional bindings for ? placeholders
     * @return static For fluent chaining
     * @throws InvalidColumnException When the expression contains dangerous patterns
     */
    public function having(string $expression, array $bindings = []): static;

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
     * Combine this query with another using UNION (deduplicates rows).
     *
     * ORDER BY and LIMIT called after union() apply to the combined result.
     * Both sides must select the same number of columns; a UnionShapeMismatchException
     * is thrown at compile time otherwise.
     *
     * Calling distinct() before union() on this builder is redundant but harmless —
     * UNION already deduplicates the combined result. It still emits SELECT DISTINCT
     * on this side of the union, which SQL engines accept.
     *
     * @param QueryBuilderInterface $other The right-hand query
     * @return static For fluent chaining
     * @throws UnionShapeMismatchException When column counts differ
     */
    public function union(QueryBuilderInterface $other): static;

    /**
     * Combine this query with another using UNION ALL (preserves duplicates).
     *
     * ORDER BY and LIMIT called after unionAll() apply to the combined result.
     * Both sides must select the same number of columns; a UnionShapeMismatchException
     * is thrown at compile time otherwise.
     *
     * Calling distinct() before unionAll() on this builder deduplicates only this
     * side of the union before the raw concatenation of the other side.
     *
     * @param QueryBuilderInterface $other The right-hand query
     * @return static For fluent chaining
     * @throws UnionShapeMismatchException When column counts differ
     */
    public function unionAll(QueryBuilderInterface $other): static;

    /**
     * Get the column count for this query (used for UNION shape validation).
     *
     * @return int Number of columns selected
     */
    public function getColumnCount(): int;

    /**
     * Compile this query to SQL and populate bindings (used when building UNION subqueries).
     *
     * @param array &$bindings Bindings array to append into
     * @return string The compiled SQL for this side of the union
     */
    public function compileSubquery(array &$bindings): string;

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
     * When $column is null, counts all rows (COUNT(*)).
     * When $column is provided, counts only non-null values in that column (COUNT(column)).
     *
     * @param string|null $column Optional column name; null counts all rows
     * @return int The count
     */
    public function count(?string $column = null): int;

    /**
     * Get the minimum value of a numeric column.
     *
     * @param string $column The column name
     * @return int|float|null The minimum value, or null if the result set is empty
     * @throws InvalidColumnException When the column name is invalid
     */
    public function min(string $column): int|float|null;

    /**
     * Get the maximum value of a numeric column.
     *
     * @param string $column The column name
     * @return int|float|null The maximum value, or null if the result set is empty
     * @throws InvalidColumnException When the column name is invalid
     */
    public function max(string $column): int|float|null;

    /**
     * Get the sum of a numeric column.
     *
     * @param string $column The column name
     * @return int|float|null The sum, or null if the result set is empty
     * @throws InvalidColumnException When the column name is invalid
     */
    public function sum(string $column): int|float|null;

    /**
     * Get the average of a numeric column.
     *
     * @param string $column The column name
     * @return int|float|null The average, or null if the result set is empty
     * @throws InvalidColumnException When the column name is invalid
     */
    public function avg(string $column): int|float|null;

    /**
     * Execute a raw SQL query with optional bindings.
     *
     * @param string $sql The raw SQL query
     * @param array $bindings Parameter bindings
     * @return array<array<string, mixed>> The result rows
     */
    public function raw(
        string $sql,
        array $bindings = [],
    ): array;
}
