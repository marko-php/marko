<?php

declare(strict_types=1);

namespace Marko\Database\Connection;

/**
 * Interface for database transaction management.
 */
interface TransactionInterface
{
    /**
     * Begin a new database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Rollback the current transaction.
     */
    public function rollback(): void;

    /**
     * Execute a callback within a transaction.
     *
     * Automatically commits on success, rolls back on exception.
     *
     * @param callable $callback The callback to execute within the transaction
     * @return mixed The return value of the callback
     */
    public function transaction(callable $callback): mixed;
}
