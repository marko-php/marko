<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Contracts\LockInterface;
use Marko\Scheduler\Exceptions\SchedulerException;

/**
 * Overlap mutex on a marko/cache atomic lock, shared by every server that uses the same
 * distributed lock store (cache-redis).
 *
 * The lock is named after the task's mutexName() and expires after the given number of
 * seconds, so a run that crashes or hangs is reclaimed. This instance keeps the owner token of
 * each lock it holds, so release() only frees its own lock, never one another server took
 * after this one expired.
 *
 * Not bound by default; bind it to TaskMutexInterface from the application module. The lock
 * factory is resolved when this class is built, so a missing or non-distributed store fails
 * before any task runs.
 */
class LockTaskMutex implements TaskMutexInterface
{
    private const string REQUESTER = 'LockTaskMutex is bound to TaskMutexInterface';

    private LockFactoryInterface $lockFactory;

    /** @var array<string, LockInterface> */
    private array $held = [];

    /**
     * @throws SchedulerException When no distributed lock store is available
     */
    public function __construct(
        private readonly DistributedLockResolver $lockResolver,
    ) {
        $this->lockFactory = $lockResolver->factory(self::REQUESTER);
    }

    /**
     * @throws SchedulerException
     */
    public function acquire(
        string $name,
        int $expiresAfterSeconds,
    ): bool {
        if (isset($this->held[$name])) {
            return false;
        }

        $lock = $this->lockFactory->create($name, $expiresAfterSeconds);

        if (!$lock->acquire()) {
            return false;
        }

        $this->held[$name] = $lock;

        return true;
    }

    public function release(
        string $name,
    ): void {
        if (!isset($this->held[$name])) {
            return;
        }

        $this->held[$name]->release();
        unset($this->held[$name]);
    }

    /**
     * @throws SchedulerException
     */
    public function exists(
        string $name,
    ): bool {
        return isset($this->held[$name])
            || $this->lockResolver->store(self::REQUESTER)->currentOwner($name) !== null;
    }
}
