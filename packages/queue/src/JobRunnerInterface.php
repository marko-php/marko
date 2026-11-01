<?php

declare(strict_types=1);

namespace Marko\Queue;

use Throwable;

/**
 * Runs one attempt of a job. The worker and the sync driver both run jobs through it,
 * so the attempt lifecycle is defined in one place.
 */
interface JobRunnerInterface
{
    /**
     * Run one attempt of the job: count the attempt, call handle(), and count an exception
     * before rethrowing it. A container-aware job holds the container only while it runs.
     *
     * @throws Throwable Whatever handle() throws
     */
    public function run(JobInterface $job): void;

    /**
     * Call the callback with the container and job envelope given to a container-aware job,
     * and release them afterwards whether the callback returns or throws.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable Whatever the callback throws
     */
    public function withContainer(
        JobInterface $job,
        callable $callback,
    ): mixed;
}
