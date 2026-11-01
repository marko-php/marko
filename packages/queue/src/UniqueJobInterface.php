<?php

declare(strict_types=1);

namespace Marko\Queue;

/**
 * A job that is queued at most once per unique id while it is pending (and, with
 * UniqueUntil::Processed, while it runs).
 *
 * Pushing it takes the atomic lock `unique-job:{class}:{uniqueId}` through
 * LockFactoryInterface (marko/cache). While the lock is held, push() and later() return a
 * skipped PushResult and queue nothing. Job provides $uniqueLockOwner and its setter, so a
 * job that extends Job only implements uniqueId(), uniqueFor() and uniqueUntil().
 */
interface UniqueJobInterface extends JobInterface
{
    /**
     * The owner token of the lock taken when this job was pushed, or null before it is pushed.
     * It travels in the job payload, so the worker can release the lock.
     */
    public ?string $uniqueLockOwner { get; }

    /**
     * What makes two pushes of this job class duplicates, for example a model id.
     */
    public function uniqueId(): string;

    /**
     * Seconds the lock lives at most. It must be greater than 0: a lock that never expires
     * would block the job forever after a crash.
     */
    public function uniqueFor(): int;

    /**
     * When the lock is released.
     */
    public function uniqueUntil(): UniqueUntil;

    /**
     * Store the owner token of the lock taken when this job is pushed.
     */
    public function setUniqueLockOwner(string $owner): void;
}
