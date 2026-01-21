<?php

declare(strict_types=1);

namespace Marko\Database\Testing;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;

/**
 * Helper class for database testing utilities.
 *
 * Provides transaction-based test isolation and common database operations.
 * Instantiate this class explicitly in your tests for clear, visible dependencies.
 *
 * Usage in a Pest test file:
 *
 * ```php
 * beforeEach(function (): void {
 *     $this->dbHelper = new DatabaseTestHelper($connection);
 *     $this->dbHelper->beginTransaction();
 * });
 *
 * afterEach(function (): void {
 *     $this->dbHelper->rollback();
 * });
 * ```
 *
 * Or in a PHPUnit TestCase:
 *
 * ```php
 * class MyDatabaseTest extends TestCase
 * {
 *     private DatabaseTestHelper $dbHelper;
 *
 *     protected function setUp(): void
 *     {
 *         parent::setUp();
 *         $this->dbHelper = new DatabaseTestHelper($this->connection);
 *         $this->dbHelper->beginTransaction();
 *     }
 *
 *     protected function tearDown(): void
 *     {
 *         $this->dbHelper->rollback();
 *         parent::tearDown();
 *     }
 * }
 * ```
 */
class DatabaseTestHelper
{
    /**
     * Whether a transaction is currently active.
     */
    private bool $transactionActive = false;

    /**
     * @param ConnectionInterface&TransactionInterface $connection A connection that supports transactions
     */
    public function __construct(
        private readonly ConnectionInterface&TransactionInterface $connection,
    ) {}

    /**
     * Begin a database transaction for test isolation.
     *
     * All database operations within the test will be wrapped in a transaction
     * that is rolled back at the end, ensuring test isolation.
     */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
        $this->transactionActive = true;
    }

    /**
     * Rollback the database transaction.
     *
     * Call this in tearDown or afterEach to ensure all changes
     * made during the test are discarded.
     */
    public function rollback(): void
    {
        if ($this->transactionActive) {
            $this->connection->rollback();
            $this->transactionActive = false;
        }
    }

    /**
     * Commit the database transaction.
     *
     * Normally you want to rollback to keep tests isolated, but sometimes
     * you may need to commit to test commit behavior itself.
     */
    public function commit(): void
    {
        if ($this->transactionActive) {
            $this->connection->commit();
            $this->transactionActive = false;
        }
    }

    /**
     * Check if a database transaction is currently active.
     */
    public function hasTransaction(): bool
    {
        return $this->transactionActive;
    }

    /**
     * Seed a table with test data.
     *
     * @param string $tableName The table to seed
     * @param array<array<string, mixed>> $rows The rows to insert
     */
    public function seedTable(
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

            $this->connection->execute($sql, array_values($row));
        }
    }

    /**
     * Truncate a table (remove all data).
     *
     * @param string $tableName The table to truncate
     */
    public function truncateTable(
        string $tableName,
    ): void {
        $this->connection->execute("DELETE FROM $tableName");
    }

    /**
     * Get the count of rows in a table.
     *
     * @param string $tableName The table name
     * @return int The number of rows
     */
    public function getTableRowCount(
        string $tableName,
    ): int {
        $result = $this->connection->query("SELECT COUNT(*) as count FROM $tableName");

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Get the underlying connection.
     */
    public function getConnection(): ConnectionInterface&TransactionInterface
    {
        return $this->connection;
    }
}
