<?php

declare(strict_types=1);

namespace Marko\Database\Testing;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use RuntimeException;

/**
 * Trait providing database testing utilities.
 *
 * This trait can be used in test cases to provide:
 * - Transaction-based test isolation (automatic rollback after each test)
 * - Helper methods for common database testing scenarios
 *
 * Usage in a Pest test file:
 *
 * ```php
 * uses(DatabaseTestCase::class);
 *
 * beforeEach(function (): void {
 *     $this->beginDatabaseTransaction($connection);
 * });
 *
 * afterEach(function (): void {
 *     $this->rollbackDatabaseTransaction();
 * });
 * ```
 *
 * Or in a PHPUnit TestCase:
 *
 * ```php
 * use Marko\Database\Testing\DatabaseTestCase;
 *
 * class MyDatabaseTest extends TestCase
 * {
 *     use DatabaseTestCase;
 *
 *     protected function setUp(): void
 *     {
 *         parent::setUp();
 *         $this->beginDatabaseTransaction($this->connection);
 *     }
 *
 *     protected function tearDown(): void
 *     {
 *         $this->rollbackDatabaseTransaction();
 *         parent::tearDown();
 *     }
 * }
 * ```
 */
trait DatabaseTestCase
{
    /**
     * The transaction-capable connection for test isolation.
     */
    private ?TransactionInterface $databaseTestTransaction = null;

    /**
     * Whether a transaction is currently active for this test.
     */
    private bool $databaseTestTransactionActive = false;

    /**
     * Begin a database transaction for test isolation.
     *
     * All database operations within the test will be wrapped in a transaction
     * that is rolled back at the end, ensuring test isolation.
     *
     * @param ConnectionInterface&TransactionInterface $connection A connection that supports transactions
     * @throws TransactionException If the connection doesn't support transactions
     */
    protected function beginDatabaseTransaction(
        ConnectionInterface&TransactionInterface $connection,
    ): void {
        $this->databaseTestTransaction = $connection;
        $this->databaseTestTransaction->beginTransaction();
        $this->databaseTestTransactionActive = true;
    }

    /**
     * Rollback the database transaction started for this test.
     *
     * This should be called in tearDown or afterEach to ensure
     * all changes made during the test are discarded.
     */
    protected function rollbackDatabaseTransaction(): void
    {
        if ($this->databaseTestTransaction !== null && $this->databaseTestTransactionActive) {
            $this->databaseTestTransaction->rollback();
            $this->databaseTestTransactionActive = false;
        }
    }

    /**
     * Commit the database transaction (use only when needed for specific tests).
     *
     * Normally you want to rollback to keep tests isolated, but sometimes
     * you may need to commit to test commit behavior itself.
     */
    protected function commitDatabaseTransaction(): void
    {
        if ($this->databaseTestTransaction !== null && $this->databaseTestTransactionActive) {
            $this->databaseTestTransaction->commit();
            $this->databaseTestTransactionActive = false;
        }
    }

    /**
     * Check if a database transaction is currently active.
     */
    protected function hasDatabaseTransaction(): bool
    {
        return $this->databaseTestTransactionActive;
    }

    /**
     * Assert that a table exists in the database.
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table name to check
     */
    protected function assertTableExists(
        ConnectionInterface $connection,
        string $tableName,
    ): void {
        // This is a helper that subclasses can override for their database
        // The actual implementation depends on the database type
        throw new RuntimeException(
            'assertTableExists must be implemented by a database-specific test case',
        );
    }

    /**
     * Assert that a table does not exist in the database.
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table name to check
     */
    protected function assertTableNotExists(
        ConnectionInterface $connection,
        string $tableName,
    ): void {
        throw new RuntimeException(
            'assertTableNotExists must be implemented by a database-specific test case',
        );
    }

    /**
     * Assert that a column exists in a table.
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table name
     * @param string $columnName The column name to check
     */
    protected function assertColumnExists(
        ConnectionInterface $connection,
        string $tableName,
        string $columnName,
    ): void {
        throw new RuntimeException(
            'assertColumnExists must be implemented by a database-specific test case',
        );
    }

    /**
     * Seed the database with test data.
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table to seed
     * @param array<array<string, mixed>> $rows The rows to insert
     */
    protected function seedTable(
        ConnectionInterface $connection,
        string $tableName,
        array $rows,
    ): void {
        foreach ($rows as $row) {
            $columns = array_keys($row);
            $placeholders = array_fill(0, count($columns), '?');

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s)',
                $tableName,
                implode(', ', $columns),
                implode(', ', $placeholders),
            );

            $connection->execute($sql, array_values($row));
        }
    }

    /**
     * Truncate a table (remove all data).
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table to truncate
     */
    protected function truncateTable(
        ConnectionInterface $connection,
        string $tableName,
    ): void {
        $connection->execute("DELETE FROM {$tableName}");
    }

    /**
     * Get the count of rows in a table.
     *
     * @param ConnectionInterface $connection The database connection
     * @param string $tableName The table name
     * @return int The number of rows
     */
    protected function getTableRowCount(
        ConnectionInterface $connection,
        string $tableName,
    ): int {
        $result = $connection->query("SELECT COUNT(*) as count FROM {$tableName}");

        return (int) ($result[0]['count'] ?? 0);
    }
}
