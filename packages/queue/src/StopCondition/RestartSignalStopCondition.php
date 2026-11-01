<?php

declare(strict_types=1);

namespace Marko\Queue\StopCondition;

use Marko\Queue\RestartSignalInterface;
use Marko\Queue\WorkerRunState;
use Marko\Queue\WorkerStopConditionInterface;
use Marko\Queue\WorkerStopReason;

/**
 * Stops the worker once queue:restart records a new restart token.
 *
 * The token is read when the condition is created, just before the worker starts, and compared on
 * every check: a restart requested earlier is ignored, and no clocks are compared across hosts.
 */
class RestartSignalStopCondition implements WorkerStopConditionInterface
{
    private readonly ?string $startToken;

    public function __construct(
        private readonly RestartSignalInterface $signal,
    ) {
        $this->startToken = $signal->current();
    }

    public function check(
        WorkerRunState $state,
    ): ?WorkerStopReason {
        return $this->signal->current() !== $this->startToken ? WorkerStopReason::RestartRequested : null;
    }
}
