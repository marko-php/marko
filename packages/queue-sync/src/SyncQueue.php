<?php

declare(strict_types=1);

namespace Marko\Queue\Sync;

use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;
use Throwable;

class SyncQueue implements QueueInterface
{
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $id = bin2hex(random_bytes(16));
        $job->setId($id);
        $job->incrementAttempts();

        try {
            $job->handle();
        } catch (Throwable $e) {
            throw JobFailedException::fromException($job::class, $e);
        }

        return $id;
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
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
        string $jobId,
        int $delay = 0,
    ): bool {
        return true;
    }
}
