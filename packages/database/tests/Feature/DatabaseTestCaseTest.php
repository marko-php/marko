<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Testing\DatabaseTestCase;
use RuntimeException;
use Throwable;

// Anonymous class that uses the trait for testing

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

describe('DatabaseTestCase Trait', function (): void {
    it('provides test helpers for database testing', function (): void {
        $testCase = new TestDatabaseTestCase();

        // Initially no transaction
        expect($testCase->testHasTransaction())->toBeFalse();
    });

    it('supports test database isolation via transactions', function (): void {
        $transactionLog = [];

        $connection = new class ($transactionLog) implements ConnectionInterface, TransactionInterface
        {
            private bool $inTransaction = false;

            public function __construct(
                private array &$log,
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

            public function beginTransaction(): void
            {
                $this->inTransaction = true;
                $this->log[] = 'BEGIN';
            }

            public function commit(): void
            {
                $this->inTransaction = false;
                $this->log[] = 'COMMIT';
            }

            public function rollback(): void
            {
                $this->inTransaction = false;
                $this->log[] = 'ROLLBACK';
            }

            public function inTransaction(): bool
            {
                return $this->inTransaction;
            }

            public function transaction(
                callable $callback,
            ): mixed {
                $this->beginTransaction();

                try {
                    $result = $callback($this);
                    $this->commit();

                    return $result;
                } catch (Throwable $e) {
                    $this->rollback();

                    throw $e;
                }
            }
        };

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

        $connection = new class ($transactionLog) implements ConnectionInterface, TransactionInterface
        {
            private bool $inTransaction = false;

            public function __construct(
                private array &$log,
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

            public function beginTransaction(): void
            {
                $this->inTransaction = true;
                $this->log[] = 'BEGIN';
            }

            public function commit(): void
            {
                $this->inTransaction = false;
                $this->log[] = 'COMMIT';
            }

            public function rollback(): void
            {
                $this->inTransaction = false;
                $this->log[] = 'ROLLBACK';
            }

            public function inTransaction(): bool
            {
                return $this->inTransaction;
            }

            public function transaction(
                callable $callback,
            ): mixed {
                $this->beginTransaction();

                try {
                    $result = $callback($this);
                    $this->commit();

                    return $result;
                } catch (Throwable $e) {
                    $this->rollback();

                    throw $e;
                }
            }
        };

        $testCase = new TestDatabaseTestCase();
        $testCase->testBeginTransaction($connection);
        $testCase->testCommit();

        expect($testCase->testHasTransaction())
            ->toBeFalse()
            ->and($transactionLog)->toBe(['BEGIN', 'COMMIT']);
    });

    it('seeds table with test data', function (): void {
        $insertedData = [];

        $connection = new class ($insertedData) implements ConnectionInterface
        {
            public function __construct(
                private array &$data,
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
                $this->data[] = ['sql' => $sql, 'bindings' => $bindings];

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

        $connection = new class ($executedSql) implements ConnectionInterface
        {
            public function __construct(
                private array &$sql,
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
                $this->sql[] = $sql;

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
