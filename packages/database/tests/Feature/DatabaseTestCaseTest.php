<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Testing\DatabaseTestCase;
use RuntimeException;

require_once __DIR__ . '/Helpers.php';

/**
 * Test wrapper class that uses the DatabaseTestCase trait.
 */
class TestDatabaseTestCase
{
    use DatabaseTestCase;

    public function testBeginTransaction(
        ConnectionInterface&TransactionInterface $connection,
    ): void {
        $this->beginDatabaseTransaction($connection);
    }

    public function testRollback(): void
    {
        $this->rollbackDatabaseTransaction();
    }

    public function testCommit(): void
    {
        $this->commitDatabaseTransaction();
    }

    public function testHasTransaction(): bool
    {
        return $this->hasDatabaseTransaction();
    }

    public function testSeedTable(
        ConnectionInterface $connection,
        string $tableName,
        array $rows,
    ): void {
        $this->seedTable($connection, $tableName, $rows);
    }

    public function testTruncateTable(
        ConnectionInterface $connection,
        string $tableName,
    ): void {
        $this->truncateTable($connection, $tableName);
    }

    public function testGetTableRowCount(
        ConnectionInterface $connection,
        string $tableName,
    ): int {
        return $this->getTableRowCount($connection, $tableName);
    }
}

/**
 * Create a stub connection that tracks executed SQL for testing.
 *
 * @param array $executedData Reference to array for tracking operations
 * @param bool $trackBindings Whether to track SQL with bindings (true) or just SQL (false)
 */
function createTrackingConnectionStub(
    array &$executedData,
    bool $trackBindings = false,
): ConnectionInterface {
    return new class ($executedData, $trackBindings) implements ConnectionInterface
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private array &$data,
            private readonly bool $trackBindings,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            return [];
        }

        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            if ($this->trackBindings) {
                $this->data[] = ['sql' => $sql, 'bindings' => $bindings];
            } else {
                $this->data[] = $sql;
            }

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 1;
        }
    };
}

describe('DatabaseTestCase Trait', function (): void {
    it('provides test helpers for database testing', function (): void {
        $testCase = new TestDatabaseTestCase();

        // Initially no transaction
        expect($testCase->testHasTransaction())->toBeFalse();
    });

    it('supports test database isolation via transactions', function (): void {
        $transactionLog = [];
        $connection = createLoggingTransactionConnection($transactionLog);
        $testCase = new TestDatabaseTestCase();

        // Begin transaction
        $testCase->testBeginTransaction($connection);
        expect($testCase->testHasTransaction())
            ->toBeTrue()
            ->and($transactionLog)->toContain('BEGIN');

        // Rollback transaction (test isolation)
        $testCase->testRollback();
        expect($testCase->testHasTransaction())
            ->toBeFalse()
            ->and($transactionLog)->toContain('ROLLBACK');
    });

    it('can commit transaction when needed', function (): void {
        $transactionLog = [];
        $connection = createLoggingTransactionConnection($transactionLog);
        $testCase = new TestDatabaseTestCase();

        $testCase->testBeginTransaction($connection);
        $testCase->testCommit();

        expect($testCase->testHasTransaction())
            ->toBeFalse()
            ->and($transactionLog)->toBe(['BEGIN', 'COMMIT']);
    });

    it('seeds table with test data', function (): void {
        $insertedData = [];
        $connection = createTrackingConnectionStub($insertedData, trackBindings: true);
        $testCase = new TestDatabaseTestCase();

        $testCase->testSeedTable($connection, 'users', [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        expect($insertedData)
            ->toHaveCount(2)
            ->and($insertedData[0]['sql'])->toContain('INSERT INTO users')
            ->and($insertedData[0]['bindings'])->toContain('John')
            ->and($insertedData[1]['bindings'])->toContain('Jane');
    });

    it('truncates table for test cleanup', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);
        $testCase = new TestDatabaseTestCase();

        $testCase->testTruncateTable($connection, 'users');

        expect($executedSql)
            ->toHaveCount(1)
            ->and($executedSql[0])->toContain('DELETE FROM users');
    });

    it('gets table row count', function (): void {
        $connection = new class () implements ConnectionInterface
        {
            public function connect(): void {}

            public function disconnect(): void {}

            public function isConnected(): bool
            {
                return true;
            }

            public function query(
                string $sql,
                array $bindings = [],
            ): array {
                if (str_contains($sql, 'COUNT(*)')) {
                    return [['count' => 42]];
                }

                return [];
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                return 0;
            }

            public function prepare(
                string $sql,
            ): StatementInterface {
                throw new RuntimeException('Not implemented');
            }

            public function lastInsertId(): int
            {
                return 0;
            }
        };

        $testCase = new TestDatabaseTestCase();
        $count = $testCase->testGetTableRowCount($connection, 'users');

        expect($count)->toBe(42);
    });
});
