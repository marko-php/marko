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
        if ($this->stickyWrite) {
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
        return $this->write->transaction($callback);
    }

    public function resetStickyState(): void
    {
        $this->stickyWrite = false;
    }
}
