<?php

declare(strict_types=1);

namespace Marko\Queue\Database;

use DateTimeImmutable;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Random\RandomException;

readonly class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private JobEnvelope $jobEnvelope,
        private string $table = 'jobs',
        private string $defaultQueue = 'default',
        private int $retryAfter = 90,
    ) {}

    /**
     * @throws RandomException|SerializationException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return $this->insertJob($job, $queue, 0);
    }

    /**
     * @throws RandomException|SerializationException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return $this->insertJob($job, $queue, $delay);
    }

    /**
     * @throws RandomException|SerializationException
     */
    private function insertJob(
        JobInterface $job,
        ?string $queue,
        int $delay,
    ): string {
        $id = $this->generateId();
        $job->setId($id);

        $now = new DateTimeImmutable();
        $availableAt = $delay > 0 ? $now->modify("+$delay seconds") : $now;

        $this->connection->execute(
            "INSERT INTO $this->table (id, queue, payload, attempts, reserved_at, available_at, created_at) VALUES (:id, :queue, :payload, :attempts, :reserved_at, :available_at, :created_at)",
            [
                'id' => $id,
                'queue' => $queue ?? $this->defaultQueue,
                'payload' => $this->jobEnvelope->wrap($job->serialize()),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $availableAt->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return $id;
    }

    /**
     * @throws RandomException
     */
    private function generateId(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff),
            random_int(0, 0xffff),
            random_int(0, 0xffff),
        );
    }

    /**
     * @throws SerializationException
     */
    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        $queueName = $queue ?? $this->defaultQueue;

        // Use transaction if the connection supports it
        if ($this->connection instanceof TransactionInterface) {
            return $this->connection->transaction(fn () => $this->popJob($queueName));
        }

        return $this->popJob($queueName);
    }

    /**
     * @throws SerializationException
     */
    private function popJob(
        string $queueName,
    ): ?JobInterface {
        $now = new DateTimeImmutable();
        $reclaimCutoff = $now->modify("-$this->retryAfter seconds");

        $skipLocked = $this->supportsSkipLocked() ? ' FOR UPDATE SKIP LOCKED' : '';

        $rows = $this->connection->query(
            "SELECT * FROM $this->table WHERE queue = :queue AND (reserved_at IS NULL OR reserved_at <= :reclaim_cutoff) AND available_at <= :now ORDER BY available_at ASC, created_at ASC LIMIT 1$skipLocked",
            [
                'queue' => $queueName,
                'now' => $now->format('Y-m-d H:i:s'),
                'reclaim_cutoff' => $reclaimCutoff->format('Y-m-d H:i:s'),
            ],
        );

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];

        $affectedRows = $this->connection->execute(
            "UPDATE $this->table SET reserved_at = :reserved_at WHERE id = :id AND (reserved_at IS NULL OR reserved_at <= :reclaim_cutoff)",
            [
                'reserved_at' => $now->format('Y-m-d H:i:s'),
                'id' => $row['id'],
                'reclaim_cutoff' => $reclaimCutoff->format('Y-m-d H:i:s'),
            ],
        );

        if ($affectedRows === 0) {
            return null;
        }

        /** @var JobInterface $job */
        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($row['payload']));
        $job->setId($row['id']);

        return $job;
    }

    private function supportsSkipLocked(): bool
    {
        return match ($this->connection->driverName()) {
            'mysql', 'pgsql' => true,
            default => false,
        };
    }

    public function size(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->defaultQueue;
        $now = new DateTimeImmutable();

        $rows = $this->connection->query(
            "SELECT COUNT(*) as count FROM $this->table WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now",
            [
                'queue' => $queueName,
                'now' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return (int) ($rows[0]['count'] ?? 0);
    }

    public function clear(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->defaultQueue;

        return $this->connection->execute(
            "DELETE FROM $this->table WHERE queue = :queue",
            [
                'queue' => $queueName,
            ],
        );
    }

    public function delete(
        string $jobId,
    ): bool {
        $affectedRows = $this->connection->execute(
            "DELETE FROM $this->table WHERE id = :id",
            [
                'id' => $jobId,
            ],
        );

        return $affectedRows > 0;
    }

    public function release(
        string $jobId,
        int $delay = 0,
    ): bool {
        $now = new DateTimeImmutable();
        $availableAt = $delay > 0 ? $now->modify("+$delay seconds") : $now;

        $affectedRows = $this->connection->execute(
            "UPDATE $this->table SET reserved_at = :reserved_at, available_at = :available_at WHERE id = :id",
            [
                'reserved_at' => null,
                'available_at' => $availableAt->format('Y-m-d H:i:s'),
                'id' => $jobId,
            ],
        );

        return $affectedRows > 0;
    }
}
