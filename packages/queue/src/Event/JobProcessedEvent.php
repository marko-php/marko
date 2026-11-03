<?php

declare(strict_types=1);

namespace Marko\Queue\Event;

use Marko\Core\Event\Event;
use Marko\Queue\JobInterface;

/**
 * Dispatched by JobRunner after a job's handle() returns without throwing.
 *
 * The worker and the sync driver both run jobs through JobRunner, so it fires for every driver.
 * An observer that throws fails the attempt, as an exception from handle() would. Keep observers
 * of this event synchronous: an async observer queues a job, and running that job fires this
 * event again.
 */
class JobProcessedEvent extends Event
{
    public string $jobClass {
        get => $this->job::class;
    }

    public ?string $jobId {
        get => $this->job->id;
    }

    public function __construct(
        public readonly JobInterface $job,
        public readonly string $queue,
    ) {}
}
