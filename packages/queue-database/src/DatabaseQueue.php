<?php

declare(strict_types=1);

namespace Marko\Queue\Database;

use DateTimeImmutable;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobIdGenerator;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
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
 * - A popped job that has already reached a RetryPolicy limit is failed
 *   through the FailedJobHandlerInterface (stored, its failed() hook called,
 *   JobFailedEvent dispatched) and deleted instead of being returned: a job
 *   past its retry deadline, or one whose earlier reservations reach its max
 *   attempts (job maxAttempts, or queue.max_attempts), so a job that always
 *   crashes its worker cannot be retried forever. A job with a retry deadline
 *   is not limited by attempts, so a polling job that releases itself many
 *   times is not failed here. This happens after the reserving transaction
 *   commits, so failed() never runs inside it. When the failed job cannot be
 *   stored, pop() throws the handler's FailedJobNotStoredException and leaves
 *   the row reserved, so it is failed again once the reservation expires.
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
        private FailedJobHandlerInterface $failedJobHandler,
        private QueryBuilderFactoryInterface $queryBuilderFactory,
        private ClockInterface $clock,
        private DatabaseTimezoneConfig $databaseTimezoneConfig,
        private RetryPolicy $retryPolicy,
        private string $table = 'jobs',
        private string $defaultQueue = 'default',
        private int $retryAfter = 90,
        private JobIdGenerator $jobIdGenerator = new JobIdGenerator(),
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
        $id = $this->jobIdGenerator->generate();
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
     * @throws FailedJobNotStoredException|LockException|SerializationException
     */
    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        $queueName = $queue ?? $this->defaultQueue;

        while (true) {
            $job = $this->connection instanceof TransactionInterface
                ? $this->connection->transaction(fn (): ?JobInterface => $this->reserveNext($queueName))
                : $this->reserveNext($queueName);

            if ($job === null) {
                return null;
            }

            $failure = $this->retryPolicy->limitReachedOnReservation($job);

            if ($failure === null) {
                return $job;
            }

            // A limit was reached before this attempt could start: fail the job, then try the next one
            $this->failExhaustedJob($job, $queueName, $failure);
        }
    }

    /**
     * Reserve the next available job, or return null when none is available.
     *
     * @throws LockException|SerializationException
     */
    private function reserveNext(
        string $queueName,
    ): ?JobInterface {
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

        return $job;
    }

    /**
     * Fail a popped job that reached a RetryPolicy limit before this attempt started, then delete it.
     *
     * The row is still reserved, so no other worker picks it up while it is being failed. A job that
     * cannot be stored is not deleted: the handler throws first.
     *
     * @throws FailedJobNotStoredException|SerializationException
     */
    private function failExhaustedJob(
        JobInterface $job,
        string $queueName,
        QueueException $failure,
    ): void {
        $this->failedJobHandler->handle($job, $queueName, $failure);
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
