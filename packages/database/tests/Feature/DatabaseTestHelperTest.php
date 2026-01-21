<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Testing\DatabaseTestHelper;
use RuntimeException;
use Throwable;

/**
 * Create a stub connection that tracks executed SQL for testing.
 *
 * @param array $executedData Reference to array for tracking operations
 * @param bool $trackBindings Whether to track SQL with bindings (true) or just SQL (false)
 */
function createTrackingConnectionStub(
    array &$executedData,
    bool $trackBindings = false,
): ConnectionInterface&TransactionInterface {
    return new class ($executedData, $trackBindings) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTransaction = false;

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
            if (str_contains($sql, 'COUNT(*)')) {
                return [['count' => 42]];
            }

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

        public function beginTransaction(): void
        {
            $this->data[] = $this->trackBindings ? ['sql' => 'BEGIN', 'bindings' => []] : 'BEGIN';
            $this->inTransaction = true;
        }

        public function commit(): void
        {
            $this->data[] = $this->trackBindings ? ['sql' => 'COMMIT', 'bindings' => []] : 'COMMIT';
            $this->inTransaction = false;
        }

        public function rollback(): void
        {
            $this->data[] = $this->trackBindings ? ['sql' => 'ROLLBACK', 'bindings' => []] : 'ROLLBACK';
            $this->inTransaction = false;
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
}

describe('DatabaseTestHelper', function (): void {
    it('requires connection in constructor for explicit dependency', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);

        expect($helper)->toBeInstanceOf(DatabaseTestHelper::class)
            ->and($helper->getConnection())->toBe($connection);
    });

    it('initially has no active transaction', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);

        expect($helper->hasTransaction())->toBeFalse();
    });

    it('begins transaction for test isolation', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);
        $helper->beginTransaction();

        expect($helper->hasTransaction())->toBeTrue()
            ->and($executedSql)->toContain('BEGIN');
    });

    it('rolls back transaction to discard test changes', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);
        $helper->beginTransaction();
        $helper->rollback();

        expect($helper->hasTransaction())->toBeFalse()
            ->and($executedSql)->toContain('ROLLBACK');
    });

    it('commits transaction when needed', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);
        $helper->beginTransaction();
        $helper->commit();

        expect($helper->hasTransaction())->toBeFalse()
            ->and($executedSql)->toBe(['BEGIN', 'COMMIT']);
    });

    it('seeds table with test data', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql, trackBindings: true);

        $helper = new DatabaseTestHelper($connection);
        $helper->seedTable('users', [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ]);

        expect($executedSql)
            ->toHaveCount(2)
            ->and($executedSql[0]['sql'])->toContain('INSERT INTO users')
            ->and($executedSql[0]['bindings'])->toContain('John')
            ->and($executedSql[1]['bindings'])->toContain('Jane');
    });

    it('truncates table for cleanup', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);
        $helper->truncateTable('users');

        expect($executedSql)
            ->toHaveCount(1)
            ->and($executedSql[0])->toContain('DELETE FROM users');
    });

    it('gets table row count', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);
        $count = $helper->getTableRowCount('users');

        expect($count)->toBe(42);
    });

    it('rollback is safe to call without active transaction', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);

        // Should not throw or add to executed SQL
        $helper->rollback();

        expect($executedSql)->toBeEmpty();
    });

    it('commit is safe to call without active transaction', function (): void {
        $executedSql = [];
        $connection = createTrackingConnectionStub($executedSql);

        $helper = new DatabaseTestHelper($connection);

        // Should not throw or add to executed SQL
        $helper->commit();

        expect($executedSql)->toBeEmpty();
    });
});
