<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use Marko\Scheduler\Exceptions\SchedulerException;

/**
 * Prevents a scheduled task from running while a previous run is still in progress.
 *
 * The mutex is keyed on the task's mutex name (ScheduledTask::mutexName()), so a background
 * child process can take it from the name and expiry on its command line alone.
 *
 * The default implementation is a local file lock. Multi-server deployments should bind
 * LockTaskMutex (a marko/cache lock on a distributed store such as cache-redis) instead.
 */
interface TaskMutexInterface
{
    /**
     * Try to take the named mutex without blocking.
     *
     * A mutex held for longer than $expiresAfterSeconds is considered stale and is reclaimed.
     *
     * @return bool True when the mutex was acquired, false when another run holds it
     * @throws SchedulerException When the mutex cannot be stored
     */
    public function acquire(
        string $name,
        int $expiresAfterSeconds,
    ): bool;

    /**
     * Release a mutex previously acquired by this instance. Does nothing if it is not held.
     *
     * @throws SchedulerException When the mutex cannot be released
     */
    public function release(
        string $name,
    ): void;

    /**
     * Whether an unexpired mutex is currently held under this name.
     *
     * @throws SchedulerException When the mutex cannot be read
     */
    public function exists(
        string $name,
    ): bool;
}
