<?php

declare(strict_types=1);

namespace Marko\Queue\Observer;

use Marko\Cache\Exceptions\LockException;
use Marko\Core\Attributes\Observer;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueJobLocks;
use Marko\Queue\UniqueUntil;

/**
 * Releases a UniqueUntil::Processed job's lock once its handle() completes.
 *
 * A job that asked to be released from inside handle() goes back on the queue, so its lock
 * stays held for the next attempt.
 */
#[Observer(event: JobProcessedEvent::class)]
readonly class ReleaseUniqueJobLockOnProcessed
{
    public function __construct(
        private UniqueJobLocks $uniqueJobLocks,
    ) {}

    /**
     * @throws QueueException|LockException
     */
    public function handle(
        JobProcessedEvent $event,
    ): void {
        $job = $event->job;

        if (
            $job instanceof UniqueJobInterface
            && $job->uniqueUntil() === UniqueUntil::Processed
            && $job->releaseDelay === null
        ) {
            $this->uniqueJobLocks->release($job);
        }
    }
}
