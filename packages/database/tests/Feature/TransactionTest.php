<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\TransactionException;
use RuntimeException;
use Throwable;

describe('Transaction Handling', function (): void {
    it('handles transactions with commit and rollback', function (): void {
        $transactionLog = [];
        $data = [];

        $connection = new class ($transactionLog, $data) implements ConnectionInterface, TransactionInterface
        {
            private bool $inTransaction = false;

            private array $pendingData = [];

            public function __construct(
                private array &$log,
                private array &$committedData,
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
                return $this->committedData;
            }

            public function execute(
                string $sql,
                array $bindings = [],
            ): int {
                if ($this->inTransaction) {
                    $this->pendingData[] = ['sql' => $sql, 'bindings' => $bindings];
                } else {
                    $this->committedData[] = ['sql' => $sql, 'bindings' => $bindings];
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
                $this->inTransaction = true;
                $this->pendingData = [];
                $this->log[] = 'BEGIN';
            }

            public function commit(): void
            {
                if (!$this->inTransaction) {
                    throw TransactionException::notInTransaction();
                }

                $this->committedData = [...$this->committedData, ...$this->pendingData];
                $this->pendingData = [];
                $this->inTransaction = false;
                $this->log[] = 'COMMIT';
            }

            public function rollback(): void
            {
                if (!$this->inTransaction) {
                    throw TransactionException::notInTransaction();
                }

                $this->pendingData = [];
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

        // Test commit flow
        $connection->beginTransaction();
        expect($connection->inTransaction())->toBeTrue();

        $connection->execute('INSERT INTO test (value) VALUES (?)', ['test1']);
        $connection->execute('INSERT INTO test (value) VALUES (?)', ['test2']);

        $connection->commit();
        expect($connection->inTransaction())
            ->toBeFalse()
            ->and($transactionLog)->toBe(['BEGIN', 'COMMIT'])
            ->and($data)->toHaveCount(2);

        // Test rollback flow
        $transactionLog = [];
        $originalCount = count($data);

        $connection->beginTransaction();
        $connection->execute('INSERT INTO test (value) VALUES (?)', ['test3']);
        $connection->rollback();

        expect($transactionLog)
            ->toBe(['BEGIN', 'ROLLBACK'])
            ->and($data)->toHaveCount($originalCount);
    });

    it('supports nested transaction simulation', function (): void {
        $log = [];

        $connection = new class ($log) implements ConnectionInterface, TransactionInterface
        {
            private int $transactionLevel = 0;

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
                $this->transactionLevel++;
                $this->log[] = "BEGIN LEVEL {$this->transactionLevel}";
            }

            public function commit(): void
            {
                if ($this->transactionLevel === 0) {
                    throw TransactionException::notInTransaction();
                }
                $this->log[] = "COMMIT LEVEL {$this->transactionLevel}";
                $this->transactionLevel--;
            }

            public function rollback(): void
            {
                if ($this->transactionLevel === 0) {
                    throw TransactionException::notInTransaction();
                }
                $this->log[] = "ROLLBACK LEVEL {$this->transactionLevel}";
                $this->transactionLevel--;
            }

            public function inTransaction(): bool
            {
                return $this->transactionLevel > 0;
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

        $connection->beginTransaction();
        $connection->beginTransaction(); // Nested
        $connection->commit(); // Commit inner
        $connection->commit(); // Commit outer

        expect($log)->toBe([
            'BEGIN LEVEL 1',
            'BEGIN LEVEL 2',
            'COMMIT LEVEL 2',
            'COMMIT LEVEL 1',
        ]);
    });

    it('executes callback within transaction with automatic commit', function (): void {
        $log = [];
        $result = null;

        $connection = new class ($log) implements ConnectionInterface, TransactionInterface
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

        $result = $connection->transaction(function ($conn) {
            $conn->execute('INSERT INTO test VALUES (?)');

            return 'success';
        });

        expect($result)
            ->toBe('success')
            ->and($log)->toBe(['BEGIN', 'COMMIT']);
    });

    it('executes callback within transaction with automatic rollback on exception', function (): void {
        $log = [];

        $connection = new class ($log) implements ConnectionInterface, TransactionInterface
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

        $exceptionThrown = false;

        try {
            $connection->transaction(function ($conn): void {
                $conn->execute('INSERT INTO test VALUES (?)');

                throw new RuntimeException('Something went wrong');
            });
        } catch (RuntimeException $e) {
            $exceptionThrown = true;
            expect($e->getMessage())->toBe('Something went wrong');
        }

        expect($exceptionThrown)
            ->toBeTrue()
            ->and($log)->toBe(['BEGIN', 'ROLLBACK']);
    });

    it('throws TransactionException when committing without active transaction', function (): void {
        $connection = new class () implements ConnectionInterface, TransactionInterface
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

            public function beginTransaction(): void {}

            public function commit(): void
            {
                throw TransactionException::notInTransaction();
            }

            public function rollback(): void
            {
                throw TransactionException::notInTransaction();
            }

            public function inTransaction(): bool
            {
                return false;
            }

            public function transaction(
                callable $callback,
            ): mixed {
                return null;
            }
        };

        expect(fn () => $connection->commit())
            ->toThrow(TransactionException::class);
    });
});
