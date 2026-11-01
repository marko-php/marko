<?php

declare(strict_types=1);

namespace Marko\Queue\Database;

use DateTimeImmutable;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Psr\Clock\ClockInterface;
use Random\RandomException;

/**
 * Database-backed queue.
 *
 * Attempt counting: the `attempts` column is the authoritative count. Every
 * reservation increments it, so it counts attempts *started*, including ones
 * whose worker died mid-run (fatal error, OOM, SIGKILL) and never released the
 * job. The `attempts` value inside the serialized payload lags behind and is
 * brought up to the column value whenever the job is popped or released:
 *
 * - pop() hands the worker a job whose attempts equal the reservations made
 *   before this one. The worker then increments it for the current run.
 * - release() stores the job the worker hands back, whose attempts the worker
 *   has already incremented to the column value, so the count and every other
 *   piece of per-job state (exceptions, retry deadline) survive the round trip.
 * - A popped job whose earlier reservations already reach its max attempts
 *   (job maxAttempts, or the queue default) is moved to the failed-job store
 *   instead of being returned, so a job that always crashes its worker cannot
 *   be retried forever.
 *
 * Reservation: pop() selects the next job with the query builder's
 * lockForUpdate()->skipLocked() inside a transaction, so concurrent workers
 * skip rows another worker has locked instead of waiting for them. The
 * reserving UPDATE is guarded on reserved_at as a second line of defence.
 * The query builder factory must build on the same connection the queue
 * uses, so the lock is taken inside the queue's transaction.
 *
 * Timestamps: the clock decides when something happens; DatabaseTimezoneConfig
 * (`database.timezone`, UTC by default) decides how it is written. Every stored
 * time and every cutoff it is compared with is converted to the database
 * timezone first, so a web process and a worker that load different PHP default
 * timezones agree, and DST changes never repeat or skip a stored hour (with the
 * default UTC zone).
 */
readonly class DatabaseQueue implements QueueInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private JobEnvelope $jobEnvelope,
        private FailedJobRepositoryInterface $failedJobRepository,
        private QueryBuilderFactoryInterface $queryBuilderFactory,
        private ClockInterface $clock,
        private DatabaseTimezoneConfig $databaseTimezoneConfig,
        private string $table = 'jobs',
        private string $defaultQueue = 'default',
        private int $retryAfter = 90,
        private int $maxAttempts = 3,
    ) {}

    /**
     * @throws RandomException|SerializationException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed($this->insertJob($job, $queue, 0));
    }

    /**
     * @throws RandomException|SerializationException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed($this->insertJob($job, $queue, $delay));
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

        $now = $this->clock->now();
        $availableAt = $this->secondsAfter($now, $delay);

        $this->connection->execute(
            "INSERT INTO {$this->table()} (id, queue, payload, attempts, reserved_at, available_at, created_at) VALUES (:id, :queue, :payload, :attempts, :reserved_at, :available_at, :created_at)",
            [
                'id' => $id,
                'queue' => $queue ?? $this->defaultQueue,
                'payload' => $this->jobEnvelope->wrap($job->serialize()),
                'attempts' => $job->attempts,
                'reserved_at' => null,
                'available_at' => $this->databaseTimezoneConfig->format($availableAt),
                'created_at' => $this->databaseTimezoneConfig->format($now),
            ],
        );

        return $id;
    }

    /**
     * The instant the given number of elapsed seconds after (or, when negative, before) $instant.
     *
     * Works on the Unix timestamp, not the wall clock: modify('-90 seconds') on a DST
     * zone's wall time across a fall-back transition lands an hour off.
     */
    private function secondsAfter(
        DateTimeImmutable $instant,
        int $seconds,
    ): DateTimeImmutable {
        return $instant->setTimestamp($instant->getTimestamp() + $seconds);
    }

    /**
     * The jobs table name quoted for the connection's SQL dialect, for the raw statements. reserveNext() passes the
     * bare name to the query builder, which quotes it itself.
     */
    private function table(): string
    {
        return $this->connection->quoteIdentifier($this->table);
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
     * @throws LockException|SerializationException
     */
    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        $queueName = $queue ?? $this->defaultQueue;

        // A false result means the reserved job had exhausted its attempts through
        // crashed reservations and was moved to the failed-job store; try the next one.
        do {
            $result = $this->connection instanceof TransactionInterface
                ? $this->connection->transaction(fn (): JobInterface|false|null => $this->reserveNext($queueName))
                : $this->reserveNext($queueName);
        } while ($result === false);

        return $result;
    }

    /**
     * Reserve the next available job.
     *
     * @return JobInterface|false|null the reserved job, null when none is available, or
     *                                 false when the reserved job was moved to failed jobs
     * @throws LockException|SerializationException
     */
    private function reserveNext(
        string $queueName,
    ): JobInterface|false|null {
        $now = $this->clock->now();
        $reclaimCutoff = $this->secondsAfter($now, -$this->retryAfter);

        $row = $this->queryBuilderFactory->create()
            ->table($this->table)
            ->where('queue', '=', $queueName)
            ->where('available_at', '<=', $this->databaseTimezoneConfig->format($now))
            ->whereRaw(
                '(reserved_at IS NULL OR reserved_at <= ?)',
                [$this->databaseTimezoneConfig->format($reclaimCutoff)],
            )
            ->orderBy('available_at')
            ->orderBy('created_at')
            ->lockForUpdate()
            ->skipLocked()
            ->first();

        if ($row === null) {
            return null;
        }

        $affectedRows = $this->connection->execute(
            "UPDATE {$this->table()} SET reserved_at = :reserved_at, attempts = attempts + 1 WHERE id = :id AND (reserved_at IS NULL OR reserved_at <= :reclaim_cutoff)",
            [
                'reserved_at' => $this->databaseTimezoneConfig->format($now),
                'id' => $row['id'],
                'reclaim_cutoff' => $this->databaseTimezoneConfig->format($reclaimCutoff),
            ],
        );

        if ($affectedRows === 0) {
            return null;
        }

        $job = $this->unwrapJob($row['payload'], (int) $row['attempts']);
        $job->setId($row['id']);

        $maxAttempts = $job->maxAttempts ?? $this->maxAttempts;

        if ($job->attempts >= $maxAttempts) {
            $this->failExhaustedJob($job, $row['queue'], $maxAttempts, $now);

            return false;
        }

        return $job;
    }

    /**
     * @throws SerializationException
     */
    private function failExhaustedJob(
        JobInterface $job,
        string $queueName,
        int $maxAttempts,
        DateTimeImmutable $now,
    ): void {
        $this->failedJobRepository->store(new FailedJob(
            id: $job->id,
            queue: $queueName,
            payload: $this->jobEnvelope->wrap($job->serialize()),
            exception: "Job $job->id exceeded max attempts after worker crash or timeout: "
                . "$job->attempts of $maxAttempts attempts were reserved without being completed or released.",
            failedAt: $now,
        ));
        $this->delete($job->id);
    }

    /**
     * Verify and unserialize a stored payload, bringing its attempt count up to the given floor.
     *
     * @throws SerializationException
     */
    private function unwrapJob(
        string $payload,
        int $attempts,
    ): JobInterface {
        /** @var JobInterface $job */
        $job = unserialize($this->jobEnvelope->verifyAndUnwrap($payload));

        while ($job->attempts < $attempts) {
            $job->incrementAttempts();
        }

        return $job;
    }

    public function size(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->defaultQueue;
        $now = $this->clock->now();

        $rows = $this->connection->query(
            "SELECT COUNT(*) as count FROM {$this->table()} WHERE queue = :queue AND reserved_at IS NULL AND available_at <= :now",
            [
                'queue' => $queueName,
                'now' => $this->databaseTimezoneConfig->format($now),
            ],
        );

        return (int) ($rows[0]['count'] ?? 0);
    }

    public function clear(
        ?string $queue = null,
    ): int {
        $queueName = $queue ?? $this->defaultQueue;

        return $this->connection->execute(
            "DELETE FROM {$this->table()} WHERE queue = :queue",
            [
                'queue' => $queueName,
            ],
        );
    }

    public function delete(
        string $jobId,
    ): bool {
        $affectedRows = $this->connection->execute(
            "DELETE FROM {$this->table()} WHERE id = :id",
            [
                'id' => $jobId,
            ],
        );

        return $affectedRows > 0;
    }

    /**
     * Release a reserved job back to the queue, storing the given job (attempts, exceptions, retry deadline)
     * in place of the payload it was reserved with.
     *
     * @throws SerializationException
     */
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        if ($job->id === null) {
            return false;
        }

        $availableAt = $this->secondsAfter($this->clock->now(), $delay);

        $affectedRows = $this->connection->execute(
            "UPDATE {$this->table()} SET payload = :payload, attempts = :attempts, reserved_at = :reserved_at, available_at = :available_at WHERE id = :id",
            [
                'payload' => $this->jobEnvelope->wrap($job->serialize()),
                'attempts' => $job->attempts,
                'reserved_at' => null,
                'available_at' => $this->databaseTimezoneConfig->format($availableAt),
                'id' => $job->id,
            ],
        );

        return $affectedRows > 0;
    }
}
