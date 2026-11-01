<?php

declare(strict_types=1);

namespace Marko\Queue;

interface QueueInterface
{
    /**
     * Queue the job to run as soon as a worker is free.
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult;

    /**
     * Queue the job to run once the given number of seconds has passed.
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult;

    public function pop(?string $queue = null): ?JobInterface;

    public function size(?string $queue = null): int;

    public function clear(?string $queue = null): int;

    public function delete(string $jobId): bool;

    /**
     * Put a reserved job back on the queue for another attempt after the given delay.
     *
     * The driver stores the job it is given (its attempts, exceptions, retry deadline and other state),
     * not the payload it originally stored. Returns false when the queue holds no reserved job with the job's id.
     */
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool;
}
