<?php

declare(strict_types=1);

namespace Marko\Queue\Tests\Fixtures;

use Marko\Queue\JobInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueInterface;

/**
 * A queue that keeps job objects in memory, with no uniqueness code of its own.
 */
class InMemoryQueue implements QueueInterface
{
    /** @var list<JobInterface> */
    public array $jobs = [];

    private int $nextId = 1;

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $id = 'job-' . $this->nextId++;
        $job->setId($id);
        $this->jobs[] = $job;

        return PushResult::pushed($id);
    }

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
        return array_shift($this->jobs);
    }

    public function size(
        ?string $queue = null,
    ): int {
        return count($this->jobs);
    }

    public function clear(
        ?string $queue = null,
    ): int {
        $count = count($this->jobs);
        $this->jobs = [];

        return $count;
    }

    public function delete(
        string $jobId,
    ): bool {
        $this->jobs = array_values(array_filter($this->jobs, fn (JobInterface $job) => $job->id !== $jobId));

        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $this->jobs[] = $job;

        return true;
    }
}
