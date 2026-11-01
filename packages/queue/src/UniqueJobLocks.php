<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Exceptions\LockException;
use Marko\Cache\Exceptions\NoDriverException as CacheNoDriverException;
use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Exceptions\QueueException;

/**
 * Takes and releases the lock of a UniqueJobInterface job. The push plugin and the lifecycle
 * observers share it, so every driver gets the same uniqueness without driver code.
 *
 * LockFactoryInterface is resolved on first use, never on construction: an app without a
 * cache driver can still push and run jobs that are not unique.
 */
class UniqueJobLocks
{
    private ?LockFactoryInterface $lockFactory = null;

    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    /**
     * The lock name shared by every push of the job with the same unique id.
     */
    public function lockName(
        UniqueJobInterface $job,
    ): string {
        return 'unique-job:' . $job::class . ':' . $job->uniqueId();
    }

    /**
     * Try to take the job's lock once. On success the owner token is stored on the job, so
     * whichever process runs or fails it can release the lock.
     *
     * @throws QueueException|LockException
     */
    public function acquire(
        UniqueJobInterface $job,
    ): bool {
        $seconds = $job->uniqueFor();

        if ($seconds <= 0) {
            throw QueueException::invalidUniqueFor($job::class, $seconds);
        }

        $lock = $this->lockFactory($job)->create($this->lockName($job), $seconds);

        if (!$lock->acquire()) {
            return false;
        }

        $job->setUniqueLockOwner($lock->owner());

        return true;
    }

    /**
     * Release the job's lock when this push still owns it. A job pushed without the lock (no
     * owner token) is left alone, and a lock that expired or was taken by a later push stays
     * as it is, because the release is checked against the owner token.
     *
     * @throws QueueException|LockException
     */
    public function release(
        UniqueJobInterface $job,
    ): void {
        $owner = $job->uniqueLockOwner;

        if ($owner === null) {
            return;
        }

        $this->lockFactory($job)->restore($this->lockName($job), $owner)->release();
    }

    /**
     * @throws QueueException
     */
    private function lockFactory(
        UniqueJobInterface $job,
    ): LockFactoryInterface {
        if ($this->lockFactory !== null) {
            return $this->lockFactory;
        }

        try {
            $this->lockFactory = $this->container->get(LockFactoryInterface::class);
        } catch (CacheNoDriverException $e) {
            throw QueueException::noLockDriver($job::class, $e);
        }

        return $this->lockFactory;
    }
}
