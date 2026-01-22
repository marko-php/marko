<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

function createTestQueueConfig(
    array $values = [],
): QueueConfig {
    $configRepository = new class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->values[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->values);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->values[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->values[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->values[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->values[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->values[$key] ?? $default);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->values;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new QueueConfig($configRepository);
}

function createTestFailedJobRepository(): FailedJobRepositoryInterface
{
    return new class () implements FailedJobRepositoryInterface
    {
        public array $storedJobs = [];

        public function store(
            FailedJob $failedJob,
        ): void {
            $this->storedJobs[$failedJob->id] = $failedJob;
        }

        public function all(): array
        {
            return array_values($this->storedJobs);
        }

        public function find(
            string $id,
        ): ?FailedJob {
            return $this->storedJobs[$id] ?? null;
        }

        public function delete(
            string $id,
        ): bool {
            if (isset($this->storedJobs[$id])) {
                unset($this->storedJobs[$id]);

                return true;
            }

            return false;
        }

        public function clear(): int
        {
            $count = count($this->storedJobs);
            $this->storedJobs = [];

            return $count;
        }

        public function count(): int
        {
            return count($this->storedJobs);
        }
    };
}

class FailingTestJob extends Job
{
    protected int $maxAttempts = 2;

    public function handle(): void
    {
        throw new RuntimeException('Job failed permanently');
    }
}

class StopTestHelper
{
    public static int $popCount = 0;

    public static ?Worker $worker = null;

    public static int $stopAfter = 3;
}

class StopTestJob extends Job
{
    public function handle(): void
    {
        // Do nothing
    }
}

class StopTestQueue implements QueueInterface
{
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return 'job-1';
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string {
        return 'job-1';
    }

    public function pop(
        ?string $queue = null,
    ): ?JobInterface {
        StopTestHelper::$popCount++;

        if (StopTestHelper::$popCount >= StopTestHelper::$stopAfter) {
            StopTestHelper::$worker?->stop();
        }

        $job = new StopTestJob();
        $job->setId('job-' . StopTestHelper::$popCount);

        return $job;
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

describe('Worker', function () {
    test('processes jobs from queue', function () {
        $job = new class () extends Job
        {
            public static bool $handled = false;

            public function handle(): void
            {
                self::$handled = true;
            }
        };
        $job->setId('job-1');
        $job::$handled = false;

        $queue = new class ($job) implements QueueInterface
        {
            private bool $popped = false;

            public bool $deleted = false;

            public function __construct(
                private JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function pop(
                ?string $queue = null,
            ): ?JobInterface {
                if ($this->popped) {
                    return null;
                }
                $this->popped = true;

                return $this->job;
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
                $this->deleted = true;

                return true;
            }

            public function release(
                string $jobId,
                int $delay = 0,
            ): bool {
                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker($queue, $failedRepository, $config);

        expect($worker)->toBeInstanceOf(WorkerInterface::class);

        $worker->work(once: true);

        expect($job::$handled)->toBeTrue();
        expect($queue->deleted)->toBeTrue();
    });

    test('handles job failures with retry', function () {
        $job = new class () extends Job
        {
            protected int $maxAttempts = 3;

            public function handle(): void
            {
                throw new RuntimeException('Job failed');
            }
        };
        $job->setId('job-1');

        $releasedDelay = null;
        $queue = new class ($job, $releasedDelay) implements QueueInterface
        {
            private bool $popped = false;

            public bool $deleted = false;

            public ?int $releasedDelay = null;

            public function __construct(
                private JobInterface $job,
                mixed &$releasedDelay,
            ) {
                $releasedDelay = &$this->releasedDelay;
            }

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function pop(
                ?string $queue = null,
            ): ?JobInterface {
                if ($this->popped) {
                    return null;
                }
                $this->popped = true;

                return $this->job;
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
                $this->deleted = true;

                return true;
            }

            public function release(
                string $jobId,
                int $delay = 0,
            ): bool {
                $this->releasedDelay = $delay;

                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker($queue, $failedRepository, $config);

        $worker->work(once: true);

        // Job should be released with exponential backoff delay (2^1 * 10 = 20 seconds)
        expect($queue->releasedDelay)->toBe(20);
        expect($queue->deleted)->toBeFalse();
        expect($failedRepository->count())->toBe(0);
    });

    test('stores failed job after max attempts', function () {
        $job = new FailingTestJob();
        $job->setId('job-1');
        // Simulate that job has already been attempted once
        $job->incrementAttempts();

        $queue = new class ($job) implements QueueInterface
        {
            private bool $popped = false;

            public bool $deleted = false;

            public bool $released = false;

            public function __construct(
                private JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function pop(
                ?string $queue = null,
            ): ?JobInterface {
                if ($this->popped) {
                    return null;
                }
                $this->popped = true;

                return $this->job;
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
                $this->deleted = true;

                return true;
            }

            public function release(
                string $jobId,
                int $delay = 0,
            ): bool {
                $this->released = true;

                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker($queue, $failedRepository, $config);

        $worker->work(once: true);

        // Job should be stored as failed and deleted from queue
        expect($queue->deleted)->toBeTrue();
        expect($queue->released)->toBeFalse();
        expect($failedRepository->count())->toBe(1);

        $failedJob = $failedRepository->find('job-1');

        expect($failedJob)->not->toBeNull();
        expect($failedJob->queue)->toBe('default');
        expect($failedJob->exception)->toContain('Job failed permanently');
    });

    test('stops when stop() is called', function () {
        // Use a static counter and worker reference to stop after 3 jobs
        StopTestHelper::$popCount = 0;
        StopTestHelper::$worker = null;
        StopTestHelper::$stopAfter = 3;

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        // Create queue that references the static helper
        $queue = new StopTestQueue();

        $worker = new Worker($queue, $failedRepository, $config);
        StopTestHelper::$worker = $worker;

        $worker->work();

        // Worker should have stopped after 3 jobs
        expect(StopTestHelper::$popCount)->toBe(3);
    });

    test('processes single job with once flag', function () {
        $popCount = 0;

        $job = new class () extends Job
        {
            public static bool $handled = false;

            public function handle(): void
            {
                self::$handled = true;
            }
        };
        $job->setId('job-1');
        $job::$handled = false;

        $queue = new class ($job, $popCount) implements QueueInterface
        {
            public function __construct(
                private JobInterface $job,
                private int &$popCount,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function pop(
                ?string $queue = null,
            ): ?JobInterface {
                $this->popCount++;

                // Always return a job (but once flag should stop after first)
                return $this->job;
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
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker($queue, $failedRepository, $config);

        // With once=true, worker should process exactly one job and return
        $worker->work(once: true);

        expect($popCount)->toBe(1);
        expect($job::$handled)->toBeTrue();
    });

    test('returns immediately when once flag is set and no jobs available', function () {
        $popCount = 0;

        $queue = new class ($popCount) implements QueueInterface
        {
            public function __construct(
                private int &$popCount,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'job-1';
            }

            public function pop(
                ?string $queue = null,
            ): ?JobInterface {
                $this->popCount++;

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
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker($queue, $failedRepository, $config);

        // With once=true and no jobs, worker should return immediately
        $worker->work(once: true);

        expect($popCount)->toBe(1);
    });
});
