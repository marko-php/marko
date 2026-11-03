<?php

declare(strict_types=1);

namespace Marko\Queue;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Queue\Event\JobProcessedEvent;
use Marko\Queue\Event\JobProcessingEvent;
use Throwable;

class JobRunner implements JobRunnerInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly JobEnvelope $jobEnvelope,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * Any release request left on the job from an earlier attempt is cleared first, so release()
     * inside handle() is the only thing that sets releaseDelay for this attempt.
     *
     * JobProcessingEvent is dispatched once the attempt is counted, just before handle(), and
     * JobProcessedEvent once handle() returns without throwing. Both run while a container-aware
     * job still holds its container. Both carry the queue the job was taken from.
     *
     * @throws Throwable
     */
    public function run(
        JobInterface $job,
        string $queue,
    ): void {
        $this->withContainer($job, function () use ($job, $queue): void {
            $job->clearRelease();
            $job->incrementAttempts();
            $this->eventDispatcher->dispatch(new JobProcessingEvent($job, $queue));

            try {
                $job->handle();
            } catch (Throwable $e) {
                $job->incrementExceptions();

                throw $e;
            }

            $this->eventDispatcher->dispatch(new JobProcessedEvent($job, $queue));
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
