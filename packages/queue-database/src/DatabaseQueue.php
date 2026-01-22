<?php

declare(strict_types=1);

namespace Marko\Queue\Database;

use DateTimeImmutable;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'jobs',
        private string $defaultQueue = 'default',
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return $this->insertJob($job, $queue, 0);
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return $this->insertJob($job, $queue, $delay);
    }

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
                'payload' => $job->serialize(),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => $availableAt->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
            ],
        );

        return $id;
    }

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

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        $queueName = $queue ?? $this->defaultQueue;
        $now = new DateTimeImmutable();

        $rows = $this->connection->query(
            "SELECT * FROM $this->table WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now ORDER BY available_at ASC, created_at ASC LIMIT 1",
            [
                'queue' => $queueName,
                'now' => $now->format('Y-m-d H:i:s'),
            ],
        );

        if ($rows === []) {
            return null;
        }

        $row = $rows[0];

        $this->connection->execute(
            "UPDATE $this->table SET reserved_at = :reserved_at, attempts = :attempts WHERE id = :id",
            [
                'reserved_at' => $now->format('Y-m-d H:i:s'),
                'attempts' => (int) $row['attempts'] + 1,
                'id' => $row['id'],
            ],
        );

        /** @var JobInterface $job */
        $job = unserialize($row['payload']);
        $job->setId($row['id']);

        // Sync attempts count with database
        for ($i = 0; $i < (int) $row['attempts'] + 1; $i++) {
            if ($job->getAttempts() <= $i) {
                $job->incrementAttempts();
            }
        }

        return $job;
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
