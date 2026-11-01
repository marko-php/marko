<?php

declare(strict_types=1);

namespace Marko\Scheduler\Mutex;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Marko\Scheduler\Exceptions\SchedulerException;
use Marko\Scheduler\ScheduledTask;

/**
 * Server claim on a marko/cache atomic lock named {mutexName}:{due minute in UTC as YmdHi}.
 *
 * The lock is never released: it expires after an hour, so a slower server cannot claim the
 * same minute after the first one has finished the task. The next due minute has a new name
 * and can be claimed at once.
 */
class LockTaskServerClaim implements TaskServerClaimInterface
{
    public const int CLAIM_EXPIRES_AFTER_SECONDS = 3600;

    public function __construct(
        private readonly DistributedLockResolver $lockResolver,
    ) {}

    /**
     * @throws SchedulerException
     */
    public function claim(
        ScheduledTask $task,
        DateTimeInterface $dueAt,
    ): bool {
        $minute = DateTimeImmutable::createFromInterface($dueAt)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('YmdHi');

        return $this->lockResolver->factory($this->requester($task))
            ->create($task->mutexName() . ':' . $minute, self::CLAIM_EXPIRES_AFTER_SECONDS)
            ->acquire();
    }

    /**
     * @throws SchedulerException
     */
    public function assertCanClaim(
        ScheduledTask $task,
    ): void {
        $this->lockResolver->factory($this->requester($task));
    }

    private function requester(
        ScheduledTask $task,
    ): string {
        return "Scheduled task '" . $task->getDescription() . "' uses onOneServer()";
    }
}
