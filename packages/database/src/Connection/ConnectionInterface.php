<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

interface ConnectionInterface
{
    public function connect(): void;

    public function disconnect(): void;

    public function isConnected(): bool;

    /**
     * Execute a SELECT query and return results.
     *
     * @param string $sql The SQL query
     * @param array<mixed> $bindings Parameter bindings
     * @return array<array<string, mixed>> Query results
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array;

    /**
     * Execute a non-SELECT statement (INSERT, UPDATE, DELETE).
     *
     * @param string $sql The SQL statement
     * @param array<mixed> $bindings Parameter bindings
     * @return int Number of affected rows
     */
    public function execute(
        string $sql,
        array $bindings = [],
    ): int;

    /**
     * Prepare a statement for execution.
     *
     * @param string $sql The SQL statement to prepare
     * @return StatementInterface The prepared statement
     */
    public function prepare(string $sql): StatementInterface;
}
