<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Container\ContainerInterface;
use Throwable;

class JobRunner implements JobRunnerInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly JobEnvelope $jobEnvelope,
    ) {}

    /**
     * Any release request left on the job from an earlier attempt is cleared first, so release()
     * inside handle() is the only thing that sets releaseDelay for this attempt.
     *
     * @throws Throwable
     */
    public function run(
        JobInterface $job,
    ): void {
        $this->withContainer($job, function () use ($job): void {
            $job->clearRelease();
            $job->incrementAttempts();

            try {
                $job->handle();
            } catch (Throwable $e) {
                $job->incrementExceptions();

                throw $e;
            }
        });
    }

    /**
     * A container-aware job releases its runtime services afterwards because a job that fails
     * for the last time is serialized into failed_jobs, and the container cannot be serialized.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     * @throws Throwable
     */
    public function withContainer(
        JobInterface $job,
        callable $callback,
    ): mixed {
        if (!$job instanceof ContainerAwareJobInterface) {
            return $callback();
        }

        try {
            $job->setContainer($this->container);
            $job->setJobEnvelope($this->jobEnvelope);

            return $callback();
        } finally {
            $job->releaseContainer();
        }
    }
}
