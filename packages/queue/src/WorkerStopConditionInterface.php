<?php

declare(strict_types=1);

namespace Marko\Queue;

/**
 * Decides whether a worker should stop. The worker checks every condition after each job and after
 * each idle pause, and stops with the first reason returned.
 *
 * WorkerOptions builds the built-in conditions from its limits; pass extra ones through
 * WorkerOptions::$stopConditions.
 */
interface WorkerStopConditionInterface
{
    /**
     * The reason to stop now, or null to keep working.
     */
    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason;
}
