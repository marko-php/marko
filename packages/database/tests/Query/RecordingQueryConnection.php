<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Query;

use Closure;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use RuntimeException;

/**
 * Records the SQL a query builder sends and reports a configurable transaction state.
 */
class RecordingQueryConnection implements ConnectionInterface, TransactionInterface
{
    public string $lastQuerySql = '';

    /** @var array<mixed> */
    public array $lastQueryBindings = [];

    public string $lastExecuteSql = '';

    public function __construct(
        public bool $open = true,
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
        $this->lastQuerySql = $sql;
        $this->lastQueryBindings = $bindings;

        return [];
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->lastExecuteSql = $sql;

        return 1;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new RuntimeException('Not implemented');
    }

    public function lastInsertId(): int
    {
        return 7;
    }

    public function driverName(): string
    {
        return 'bracket';
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '[' . $identifier . ']';
    }

    public function beginTransaction(): void
    {
        $this->open = true;
    }

    public function commit(): void
    {
        $this->open = false;
    }

    public function rollback(): void
    {
        $this->open = false;
    }

    public function inTransaction(): bool
    {
        return $this->open;
    }

    public function transactionLevel(): int
    {
        return $this->open ? 1 : 0;
    }

    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        return $callback();
    }

    public function afterCommit(
        callable $callback,
    ): void {}

    public function afterRollback(
        callable $callback,
    ): void {}
}
