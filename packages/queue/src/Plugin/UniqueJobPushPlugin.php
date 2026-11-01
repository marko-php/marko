<?php

declare(strict_types=1);

namespace Marko\Queue\Plugin;

use Marko\Cache\Exceptions\LockException;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Marko\Queue\UniqueJobInterface;
use Marko\Queue\UniqueJobLocks;

/**
 * Skips pushing a UniqueJobInterface job while another push of it holds its lock.
 *
 * It sits on QueueInterface, so every driver (and any QueueInterface the container builds)
 * gets uniqueness with no driver code. A skipped push returns PushResult::skipped() naming
 * the lock; the caller can always tell the job was not queued.
 */
#[Plugin(target: QueueInterface::class)]
readonly class UniqueJobPushPlugin
{
    /**
     * Runs after the other push plugins (RetryDeadlinePlugin is 0): the lock is taken last, right
     * before the driver stores the job, so an earlier plugin that throws never leaves it held.
     */
    public const int SORT_ORDER = 100;

    public function __construct(
        private UniqueJobLocks $uniqueJobLocks,
    ) {}

    /**
     * @throws QueueException|LockException
     */
    #[Before(sortOrder: self::SORT_ORDER, method: 'push')]
    public function beforePush(
        JobInterface $job,
        ?string $queue = null,
    ): ?PushResult {
        return $this->gate($job);
    }

    /**
     * @throws QueueException|LockException
     */
    #[Before(sortOrder: self::SORT_ORDER, method: 'later')]
    public function beforeLater(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): ?PushResult {
        return $this->gate($job);
    }

    /**
     * Null lets the push through; a PushResult short-circuits it.
     *
     * @throws QueueException|LockException
     */
    private function gate(
        JobInterface $job,
    ): ?PushResult {
        if (!$job instanceof UniqueJobInterface || $this->uniqueJobLocks->acquire($job)) {
            return null;
        }

        $lockName = $this->uniqueJobLocks->lockName($job);

        return PushResult::skipped(
            "Unique job lock \"$lockName\" is held: a " . $job::class
            . " with unique id \"{$job->uniqueId()}\" is already queued or running.",
        );
    }
}
