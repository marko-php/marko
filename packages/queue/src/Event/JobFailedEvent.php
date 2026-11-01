<?php

declare(strict_types=1);

namespace Marko\Queue\Event;

use Marko\Core\Event\Event;
use Marko\Queue\JobInterface;
use Throwable;

/**
 * Dispatched by FailedJobHandler when a job fails for the last time, after the job is stored
 * as failed and its failed() hook (if any) has run. It is dispatched even when failed() throws.
 */
class JobFailedEvent extends Event
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
        public readonly Throwable $exception,
    ) {}
}
