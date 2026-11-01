<?php

declare(strict_types=1);

namespace Marko\Queue;

use DateTimeImmutable;

/**
 * A snapshot of a running worker that each WorkerStopConditionInterface checks.
 */
readonly class WorkerRunState
{
    /**
     * @param DateTimeImmutable $startedAt When work() started, from the worker's clock
     * @param int $jobsProcessed Jobs processed since work() started
     * @param int $memoryBytes Memory allocated to the process, in bytes
     */
    public function __construct(
        public DateTimeImmutable $startedAt,
        public int $jobsProcessed,
        public int $memoryBytes,
    ) {}
}
