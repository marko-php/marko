<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;

interface JobInterface
{
    public ?string $id { get; }

    public int $attempts { get; }

    /**
     * Maximum attempts for this job, or null to use the `queue.max_attempts` config default.
     */
    public ?int $maxAttempts { get; }

    /**
     * Seconds to wait before retrying this job after a failed attempt.
     *
     * An int is a fixed delay for every retry. A list gives the delay per attempt
     * (the first entry after attempt 1, the second after attempt 2, ...), and its
     * last value repeats once the list runs out. Null uses the `queue.backoff` config.
     *
     * @var int|list<int>|null
     */
    public array|int|null $backoff { get; }

    /**
     * Seconds one attempt of this job may run, or null to use the worker's timeout.
     */
    public ?int $timeout { get; }

    /**
     * Attempts that may end in an exception before the job fails, or null for no exception limit.
     */
    public ?int $maxExceptions { get; }

    /**
     * Attempts of this job that ended in an exception. Deliberate releases are not counted.
     */
    public int $exceptions { get; }

    /**
     * The time after which this job is no longer retried, captured once when it is dispatched,
     * or null when the job has no retry deadline.
     */
    public ?DateTimeImmutable $retryDeadline { get; }

    /**
     * Seconds to wait before running the job again, set when handle() calls release(),
     * or null when the job did not ask to be released.
     */
    public ?int $releaseDelay { get; }

    public function handle(): void;

    public function setId(string $id): void;

    public function incrementAttempts(): void;

    public function resetAttempts(): void;

    /**
     * Record that an attempt of this job ended in an exception.
     */
    public function incrementExceptions(): void;

    /**
     * Store the retry deadline captured when the job is dispatched. It is not re-evaluated on later attempts.
     */
    public function setRetryDeadline(DateTimeImmutable $deadline): void;

    /**
     * Ask, from inside handle(), to be released back to the queue after the given delay, without throwing.
     */
    public function release(int $delaySeconds = 0): void;

    /**
     * Withdraw a release request, so the next attempt starts without one.
     */
    public function clearRelease(): void;

    public function serialize(): string;

    public static function unserialize(string $data): static;
}
