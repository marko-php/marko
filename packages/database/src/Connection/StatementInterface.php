<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Interface for prepared statements.
 */
interface StatementInterface
{
    /**
     * Execute the prepared statement with bindings.
     *
     * @param array $bindings Parameter bindings
     * @return bool True on success
     */
    public function execute(array $bindings = []): bool;

    /**
     * Fetch all results as an array.
     *
     * @return array<array<string, mixed>> Query results
     */
    public function fetchAll(): array;

    /**
     * Fetch the next row from the result set.
     *
     * @return array<string, mixed>|null The row or null if no more rows
     */
    public function fetch(): ?array;

    /**
     * Get the number of affected rows.
     *
     * @return int Number of affected rows
     */
    public function rowCount(): int;
}
