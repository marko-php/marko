<?php

declare(strict_types=1);

namespace Marko\Queue\StopCondition;

use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;

/**
 * Stops the worker once it has processed --max-jobs jobs.
 */
class MaxJobsStopCondition implements WorkerStopConditionInterface
{
    public function __construct(
        private readonly int $maxJobs,
    ) {}

    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason {
        return $state->jobsProcessed >= $this->maxJobs ? WorkerStopReason::MaxJobs : null;
    }
}
