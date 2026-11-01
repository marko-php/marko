<?php

declare(strict_types=1);

namespace Marko\Queue\StopCondition;

use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;

/**
 * Stops the worker once its memory reaches --memory megabytes.
 */
class MemoryStopCondition implements WorkerStopConditionInterface
{
    public function __construct(
        private readonly int $megabytes,
    ) {}

    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason {
        return $state->memoryBytes >= $this->megabytes * 1024 * 1024 ? WorkerStopReason::Memory : null;
    }
}
