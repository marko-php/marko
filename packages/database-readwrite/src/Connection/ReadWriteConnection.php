<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Connection;

use Marko\Core\Exceptions\MarkoException;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\ReadWrite\Exceptions\ReadException;
use Marko\Database\ReadWrite\Replica\ReplicaSelectorInterface;
use PDOException;

class ReadWriteConnection implements ConnectionInterface, TransactionInterface
{
    private bool $stickyWrite = false;

    /**
     * @param ConnectionInterface[] $replicas
     */
    public function __construct(
        private ConnectionInterface&TransactionInterface $write,
        private array $replicas,
        private ReplicaSelectorInterface $replicaSelector,
    ) {}

    /**
     * @throws ReadException
     */
    public function query(
        string $sql,
        array $bindings = [],
    ): array {
        if ($this->stickyWrite || $this->isWriteStatement($sql)) {
            return $this->write->query($sql, $bindings);
        }

        // Try each replica in turn, falling back on PDOException or MarkoException.
        // NOTE: WeightedReplicaSelector weights are based on original indices; they
        // do not rebalance when replicas are removed during fallback (known v1 limitation).
        $remaining = $this->replicas;
        $failures = [];

        while ($remaining !== []) {
            $replica = $this->replicaSelector->select($remaining);

            try {
                return $replica->query($sql, $bindings);
            } catch (PDOException $e) {
                $failures[] = $e->getMessage();
                $remaining = array_values(array_filter($remaining, fn ($r) => $r !== $replica));
            } catch (MarkoException $e) {
                $failures[] = $e->getMessage();
                $remaining = array_values(array_filter($remaining, fn ($r) => $r !== $replica));
            }
        }

        throw ReadException::allReplicasFailed($failures);
    }

    public function execute(
        string $sql,
        array $bindings = [],
    ): int {
        $this->stickyWrite = true;

        return $this->write->execute($sql, $bindings);
    }

    public function prepare(string $sql): StatementInterface
    {
        return $this->write->prepare($sql);
    }

    public function lastInsertId(): int
    {
        return $this->write->lastInsertId();
    }

    public function driverName(): string
    {
        return $this->write->driverName();
    }

    public function connect(): void
    {
        $this->write->connect();
    }

    public function disconnect(): void
    {
        $this->write->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->write->isConnected();
    }

    public function beginTransaction(): void
    {
        $this->stickyWrite = true;
        $this->write->beginTransaction();
    }

    public function commit(): void
    {
        $this->write->commit();
    }

    public function rollback(): void
    {
        $this->write->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->write->inTransaction();
    }

    public function transaction(callable $callback): mixed
    {
        $this->stickyWrite = true;

        try {
            return $this->write->transaction($callback);
        } finally {
            $this->stickyWrite = false;
        }
    }

    public function resetStickyState(): void
    {
        $this->stickyWrite = false;
    }

    /**
     * Detects whether a SQL statement is a write operation (INSERT, UPDATE, DELETE).
     *
     * Leading whitespace and a leading SQL line comment (-- ...) or block comment
     * (/* ... *\/) are stripped before sniffing the first keyword, case-insensitively.
     *
     * NOTE (v1 limitation): CTEs — a leading WITH clause whose final DML is INSERT/
     * UPDATE/DELETE — are NOT detected here and will route to a replica. Use execute()
     * or beginTransaction()/commit() for write CTEs, or call resetStickyState() after
     * routing to ensure correct behaviour. With ... INSERT ... RETURNING should use
     * execute() instead.
     */
    private function isWriteStatement(string $sql): bool
    {
        $trimmed = ltrim($sql);

        // Strip a leading line comment: -- ...
        if (str_starts_with($trimmed, '--')) {
            $trimmed = ltrim(substr($trimmed, (int) strpos($trimmed, "\n") + 1));
        }

        // Strip a leading block comment: /* ... */
        if (str_starts_with($trimmed, '/*')) {
            $end = strpos($trimmed, '*/');
            $trimmed = $end !== false ? ltrim(substr($trimmed, $end + 2)) : $trimmed;
        }

        return (bool) preg_match('/^(INSERT|UPDATE|DELETE)\b/i', $trimmed);
    }
}
