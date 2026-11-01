<?php

declare(strict_types=1);

namespace Marko\Queue\StopCondition;

use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;
use Psr\Clock\ClockInterface;

/**
 * Stops the worker once it has run for --max-time seconds, so a supervisor recycles it.
 */
class MaxTimeStopCondition implements WorkerStopConditionInterface
{
    public function __construct(
        private readonly int $seconds,
        private readonly ClockInterface $clock,
    ) {}

    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason {
        $elapsed = $this->clock->now()->getTimestamp() - $state->startedAt->getTimestamp();

        return $elapsed >= $this->seconds ? WorkerStopReason::MaxTime : null;
    }
}
