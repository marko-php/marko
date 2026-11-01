<?php

declare(strict_types=1);

namespace Marko\Queue\Sync;

use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;
use Random\RandomException;
use Throwable;

readonly class SyncQueue implements QueueInterface
{
    public function __construct(
        private JobRunnerInterface $jobRunner,
    ) {}

    /**
     * Run the job immediately through the shared JobRunner, exactly as a worker runs one attempt:
     * container-aware jobs (such as AsyncObserverJob) get the container and job envelope first
     * and release them once handle() returns or throws.
     *
     * @throws JobFailedException|RandomException
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
            throw JobFailedException::fromException($job::class, $e);
        }

        return PushResult::pushed($id);
    }

    /**
     * @throws JobFailedException|RandomException
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
