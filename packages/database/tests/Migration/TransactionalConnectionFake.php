<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Migration;

use Closure;
use LogicException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionalDdlInterface;
use Marko\Database\Connection\TransactionInterface;
use Throwable;

/**
 * A connection double that supports transactions, records the statements run
 * inside each transaction() call and discards them when the callback throws.
 */
class TransactionalConnectionFake implements ConnectionInterface, TransactionInterface, TransactionalDdlInterface
{
    /**
     * Statements that took effect: run outside a transaction, or inside one that committed.
     *
     * @var list<string>
     */
    public array $committed = [];

    /**
     * Statements run inside each transaction() call, in call order, whether it committed or not.
     *
     * @var list<list<string>>
     */
    public array $transactions = [];

    /** @var list<string>|null */
    private ?array $open = null;

    /**
     * @param list<string> $applied Names the migrations table already holds, as one batch
     * @param Throwable|null $commitFailure Thrown by transaction() after the callback returned, as a failing COMMIT
     * @param array<string, Throwable> $failingStatements Thrown by execute() for the statement used as key
     */
    public function __construct(
        private readonly bool $supportsTransactionalDdl = true,
        private readonly array $applied = [],
        private readonly ?Throwable $commitFailure = null,
        private readonly array $failingStatements = [],
    ) {}

    public function supportsTransactionalDdl(): bool
    {
        return $this->supportsTransactionalDdl;
    }

    public function transaction(
        callable $callback,
        int $attempts = 1,
        int|Closure|null $backoff = null,
    ): mixed {
        $this->open = [];

        try {
            $result = $callback();
        } catch (Throwable $e) {
            $this->transactions[] = $this->open;
            $this->open = null;

            throw $e;
        }

        if ($this->commitFailure !== null) {
            $this->transactions[] = $this->open;
            $this->open = null;

            throw $this->commitFailure;
        }

        $this->transactions[] = $this->open;
        $this->committed = [...$this->committed, ...$this->open];
        $this->open = null;

        return $result;
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        if (isset($this->failingStatements[$sql])) {
            throw $this->failingStatements[$sql];
        }

        if ($this->open === null) {
            $this->committed[] = $sql;
        } else {
            $this->open[] = $sql;
        }

        return 1;
    }

    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        if (str_contains($sql, 'MAX(batch)')) {
            return [['max_batch' => $this->applied === [] ? null : 1]];
        }

        return array_map(
            fn (string $name): array => ['name' => $name],
            $this->applied,
        );
    }

    public function driverName(): string
    {
        return 'fake';
    }

    public function quoteIdentifier(
        string $identifier,
    ): string {
        return '"' . $identifier . '"';
    }

    public function connect(): void {}

    public function disconnect(): void {}

    public function isConnected(): bool
    {
        return true;
    }

    public function prepare(
        string $sql,
    ): StatementInterface {
        throw new LogicException('Migrations do not prepare statements');
    }

    public function lastInsertId(): int
    {
        return 0;
    }

    public function supportsReturning(): bool
    {
        return false;
    }

    public function beginTransaction(): void {}

    public function commit(): void {}

    public function rollback(): void {}

    public function inTransaction(): bool
    {
        return $this->open !== null;
    }

    public function transactionLevel(): int
    {
        return $this->open === null ? 0 : 1;
    }

    public function afterCommit(callable $callback): void {}

    public function afterRollback(callable $callback): void {}
}
