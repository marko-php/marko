<?php

declare(strict_types=1);

namespace Marko\Queue\Sync;

use Marko\Queue\Exceptions\FailedJobNotStoredException;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Random\RandomException;
use Throwable;

readonly class SyncQueue implements QueueInterface
{
    public function __construct(
        private JobRunnerInterface $jobRunner,
        private FailedJobHandlerInterface $failedJobHandler,
        private QueueConfig $config,
    ) {}

    /**
     * Run the job immediately through the shared JobRunner, exactly as a worker runs one attempt:
     * container-aware jobs (such as AsyncObserverJob) get the container and job envelope first
     * and release them once handle() returns or throws.
     *
     * A job that throws has no further attempts, so it goes through the failed-job handler
     * (its failed() hook runs and JobFailedEvent is dispatched) before JobFailedException is thrown.
     * When a bound failed-job repository cannot store it, the handler's FailedJobNotStoredException is
     * thrown instead, and failed() and the event do not run.
     *
     * @throws FailedJobNotStoredException|JobFailedException|RandomException|SerializationException
     */
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $id = bin2hex(random_bytes(16));
        $job->setId($id);

        try {
            $this->jobRunner->run($job);
        } catch (Throwable $e) {
            $this->failedJobHandler->handle($job, $queue ?? $this->config->queue(), $e);

            throw JobFailedException::fromException($job::class, $e);
        }

        return PushResult::pushed($id);
    }

    /**
     * @throws FailedJobNotStoredException|JobFailedException|RandomException|SerializationException
     */
    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return $this->push($job, $queue);
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        return null;
    }

    public function size(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function clear(
        ?string $queue = null,
    ): int {
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        return true;
    }
}
