<?php

declare(strict_types=1);

namespace Marko\Queue\Tests\Command;

use Marko\Core\Command\Output;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

/**
 * Stub FailedJobRepository for testing.
 */
class StubFailedJobRepository implements FailedJobRepositoryInterface
{
    /** @var array<string> */
    public array $deletedIds = [];

    /**
     * @param array<FailedJob> $failedJobs
     */
    public function __construct(
        private array $failedJobs = [],
    ) {}

    public function store(
        FailedJob $failedJob,
    ): void {
        $this->failedJobs[] = $failedJob;
    }

    public function all(): array
    {
        return $this->failedJobs;
    }

    public function find(
        string $id,
    ): ?FailedJob {
        return array_find($this->failedJobs, fn ($failedJob) => $failedJob->id === $id);
    }

    public function delete(
        string $id,
    ): bool {
        foreach ($this->failedJobs as $index => $failedJob) {
            if ($failedJob->id === $id) {
                unset($this->failedJobs[$index]);
                $this->deletedIds[] = $id;

                return true;
            }
        }

        return false;
    }

    public function clear(): int
    {
        $count = count($this->failedJobs);
        $this->failedJobs = [];

        return $count;
    }

    public function count(): int
    {
        return count($this->failedJobs);
    }
}

/**
 * Stub Queue for testing.
 */
class StubQueue implements QueueInterface
{
    /** @var array<array{job: JobInterface, queue: ?string}> */
    public array $pushedJobs = [];

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        $this->pushedJobs[] = ['job' => $job, 'queue' => $queue];

        return 'new-job-id';
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return 'delayed-job-id';
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

/**
 * Command test helpers.
 */
final class Helpers
{
    /**
     * Helper to capture command output.
     *
     * @return array{stream: resource, output: Output}
     */
    public static function createOutputStream(): array
    {
        $stream = fopen('php://memory', 'r+');

        return [
            'stream' => $stream,
            'output' => new Output($stream),
        ];
    }

    /**
     * Helper to get output content from stream.
     *
     * @param resource $stream
     */
    public static function getOutputContent(
        mixed $stream,
    ): string {
        rewind($stream);

        return stream_get_contents($stream);
    }

    /**
     * Create a stub FailedJobRepositoryInterface.
     *
     * @param array<FailedJob> $failedJobs
     */
    public static function createStubFailedJobRepository(
        array $failedJobs = [],
    ): StubFailedJobRepository {
        return new StubFailedJobRepository($failedJobs);
    }

    /**
     * Create a stub QueueInterface.
     */
    public static function createStubQueue(): StubQueue
    {
        return new StubQueue();
    }
}
