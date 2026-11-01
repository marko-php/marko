<?php

declare(strict_types=1);

namespace Marko\Queue\Observer;

use Marko\Cache\Exceptions\LockException;
use Marko\Core\Attributes\Observer;
use Marko\Queue\Event\JobProcessingEvent;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueJobLocks;
use Marko\Queue\UniqueUntil;

/**
 * Releases a UniqueUntil::Processing job's lock as soon as a worker (or the sync driver)
 * starts it, so a copy can be queued while it runs.
 */
#[Observer(event: JobProcessingEvent::class)]
readonly class ReleaseUniqueJobLockOnProcessing
{
    public function __construct(
        private UniqueJobLocks $uniqueJobLocks,
    ) {}

    /**
     * @throws QueueException|LockException
     */
    public function handle(
        JobProcessingEvent $event,
    ): void {
        $job = $event->job;

        if ($job instanceof UniqueJobInterface && $job->uniqueUntil() === UniqueUntil::Processing) {
            $this->uniqueJobLocks->release($job);
        }
    }
}
