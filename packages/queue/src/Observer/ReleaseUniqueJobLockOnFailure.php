<?php

declare(strict_types=1);

namespace Marko\Queue\Observer;

use Marko\Cache\Exceptions\LockException;
use Marko\Core\Attributes\Observer;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueJobLocks;

/**
 * Releases a unique job's lock when it fails for the last time: in the worker (including a
 * timeout, before the worker exits), at pop time in a driver that fails exhausted jobs, or
 * in the sync driver. Every one of those goes through FailedJobHandler, which dispatches
 * JobFailedEvent. A retry (release) is not a final failure, so the lock stays held across it.
 *
 * A UniqueUntil::Processing job's lock was already released when it started; the owner
 * check makes the second release a no-op, even when a later push has taken the lock since.
 */
#[Observer(event: JobFailedEvent::class)]
readonly class ReleaseUniqueJobLockOnFailure
{
    public function __construct(
        private UniqueJobLocks $uniqueJobLocks,
    ) {}

    /**
     * @throws QueueException|LockException
     */
    public function handle(
        JobFailedEvent $event,
    ): void {
        $job = $event->job;

        if ($job instanceof UniqueJobInterface) {
            $this->uniqueJobLocks->release($job);
        }
    }
}
