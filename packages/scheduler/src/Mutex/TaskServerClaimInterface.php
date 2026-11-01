<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use DateTimeInterface;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledTask;

/**
 * Decides which server runs an onOneServer() task in a given due minute.
 *
 * Every server running schedule:run calls claim() for the same task and minute; exactly one
 * of them gets true and runs the task, the others skip it.
 */
interface TaskServerClaimInterface
{
    /**
     * Claim the task's due minute for this server.
     *
     * @return bool True for exactly one caller per task per due minute
     * @throws SchedulerException When the claim cannot be stored
     */
    public function claim(
        ScheduledTask $task,
        DateTimeInterface $dueAt,
    ): bool;

    /**
     * Check, before any task runs, that claims for this task would exclude every other server.
     *
     * @throws SchedulerException When this deployment cannot guarantee one server per due minute
     */
    public function assertCanClaim(
        ScheduledTask $task,
    ): void;
}
