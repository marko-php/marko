<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Feature;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use RuntimeException;
use Throwable;

/**
 * Create a logging transaction connection for testing.
 *
 * @param array<string> $log Reference to log array for tracking operations
 *
 * @return ConnectionInterface&TransactionInterface
 *
 * @throws Throwable
 *
 * @noinspection PhpIncompatibleReturnTypeInspection - Anonymous class implements intersection type
 */
function createLoggingTransactionConnection(
    array &$log,
): ConnectionInterface&TransactionInterface {
    return new class ($log) implements ConnectionInterface, TransactionInterface
    {
        private bool $inTransaction = false;

        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
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
}
