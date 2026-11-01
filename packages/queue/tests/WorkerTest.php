<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\ContainerAwareJobInterface;
use Marko\Queue\Event\JobFailedEvent;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\FailableJobInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;

function createWorkerTestEnvelope(
    string $key = 'test-key-for-worker',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

function createWorkerStubContainer(object $observer): ContainerInterface
{
    return new readonly class ($observer) implements ContainerInterface
    {
        public function __construct(
            private object $observer,
        ) {}

        public function get(string $id): object
        {
            return $this->observer;
        }

        public function has(string $id): bool
        {
            return true;
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {}

        public function call(Closure $callable): mixed
        {
            return null;
        }

        public function resolvedInstances(?string $interface = null): array
        {
            return [];
        }
    };
}

function createNullWorkerContainer(): ContainerInterface
{
    return new class () implements ContainerInterface
    {
        public function get(string $id): never
        {
            throw new RuntimeException("No container binding for: $id");
        }

        public function has(string $id): bool
        {
            return false;
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {}

        public function call(Closure $callable): mixed
        {
            return null;
        }

        public function resolvedInstances(?string $interface = null): array
        {
            return [];
        }
    };
}

function createTestQueueConfig(
    array $values = [],
): QueueConfig {
    // Provide default config values (simulating what config/queue.php provides)
    $defaults = [
        'queue.driver' => 'sync',
        'queue.connection' => 'default',
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
    ];
    $values = array_merge($defaults, $values);

    return new QueueConfig(new FakeConfigRepository($values));
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
    public protected(set) ?int $maxAttempts = 2;

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
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
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
        JobInterface $job,
        int $delay = 0,
    ): bool {
        return true;
    }
}

describe('Worker', function (): void {
    test('processes jobs from queue', function (): void {
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
                private readonly JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        expect($worker)->toBeInstanceOf(WorkerInterface::class);

        $worker->work(once: true);

        expect($job::$handled)->toBeTrue()
            ->and($queue->deleted)->toBeTrue();
    });

    test('handles job failures with retry', function (): void {
        $job = new class () extends Job
        {
            public protected(set) ?int $maxAttempts = 3;

            public function handle(): void
            {
                throw new RuntimeException('Job failed');
            }
        };
        $job->setId('job-1');

        $queue = new class ($job) implements QueueInterface
        {
            private bool $popped = false;

            public bool $deleted = false;

            public ?int $releasedDelay = null;

            public function __construct(
                private readonly JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                $this->releasedDelay = $delay;

                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        $worker->work(once: true);

        // Job should be released with exponential backoff delay (2^1 * 10 = 20 seconds)
        expect($queue->releasedDelay)->toBe(20)
            ->and($queue->deleted)->toBeFalse()
            ->and($failedRepository->count())->toBe(0);
    });

    test('stores failed job after max attempts', function (): void {
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
                private readonly JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                $this->released = true;

                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        $worker->work(once: true);

        // Job should be stored as failed and deleted from queue
        expect($queue->deleted)->toBeTrue()
            ->and($queue->released)->toBeFalse()
            ->and($failedRepository->count())->toBe(1);

        $failedJob = $failedRepository->find('job-1');

        expect($failedJob)->not->toBeNull()
            ->and($failedJob->queue)->toBe('default')
            ->and($failedJob->exception)->toContain('Job failed permanently');
    });

    test('stops when stop() is called', function (): void {
        // Use a static counter and worker reference to stop after 3 jobs
        StopTestHelper::$popCount = 0;
        StopTestHelper::$worker = null;
        StopTestHelper::$stopAfter = 3;

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        // Create queue that references the static helper
        $queue = new StopTestQueue();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );
        StopTestHelper::$worker = $worker;

        $worker->work();

        // Worker should have stopped after 3 jobs
        expect(StopTestHelper::$popCount)->toBe(3);
    });

    test('processes single job with once flag', function (): void {
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
                private readonly JobInterface $job,
                private int &$popCount,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        // With once=true, worker should process exactly one job and return
        $worker->work(once: true);

        expect($popCount)->toBe(1)
            ->and($job::$handled)->toBeTrue();
    });

    test('returns immediately when once flag is set and no jobs available', function (): void {
        $popCount = 0;

        $queue = new class ($popCount) implements QueueInterface
        {
            public function __construct(
                private int &$popCount,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        // With once=true and no jobs, worker should return immediately
        $worker->work(once: true);

        expect($popCount)->toBe(1);
    });

    test('uses exponential backoff', function (): void {
        // Test that retry delay follows formula: 2^attempts * 10 seconds
        // After 1st attempt (attempts=1): 2^1 * 10 = 20 seconds
        // After 2nd attempt (attempts=2): 2^2 * 10 = 40 seconds
        // After 3rd attempt (attempts=3): 2^3 * 10 = 80 seconds

        $capture = (object) ['releasedDelays' => []];

        $job = new class () extends Job
        {
            public protected(set) ?int $maxAttempts = 5;

            public function handle(): void
            {
                throw new RuntimeException('Intentional failure');
            }
        };

        $queue = new class ($job, $capture) implements QueueInterface
        {
            private int $popCount = 0;

            public function __construct(
                private readonly JobInterface $job,
                private readonly object $capture,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('job-1');
            }

            public function pop(
                ?string $queue = null,
                bool $block = false,
            ): ?JobInterface {
                $this->popCount++;
                if ($this->popCount > 3) {
                    return null;
                }
                // Return the same job to simulate re-processing after release
                $this->job->setId('job-' . $this->popCount);

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
                JobInterface $job,
                int $delay = 0,
            ): bool {
                $this->capture->releasedDelays[] = $delay;

                return true;
            }
        };

        $failedRepository = createTestFailedJobRepository();
        $config = createTestQueueConfig();

        $worker = new Worker(
            $queue,
            new FailedJobHandler(
                $failedRepository,
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );

        // Process jobs (once each)
        $worker->work(once: true); // 1st attempt
        $worker->work(once: true); // 2nd attempt
        $worker->work(once: true); // 3rd attempt

        // Verify exponential backoff: 2^attempts * 10
        expect($capture->releasedDelays)->toHaveCount(3)
            ->and($capture->releasedDelays[0])->toBe(20)  // 2^1 * 10 = 20
            ->and($capture->releasedDelays[1])->toBe(40)  // 2^2 * 10 = 40
            ->and($capture->releasedDelays[2])->toBe(80); // 2^3 * 10 = 80
    });

    test(
        'constructs a Worker with a ContainerInterface dependency (added after the existing JobEnvelope dep) and the worker injects both the container and the JobEnvelope into a popped AsyncObserverJob before calling handle()',
        function (): void {
            $capture = (object) ['called' => false, 'event' => null];

            $observer = new readonly class ($capture)
            {
                public function __construct(
                    private object $capture,
                ) {}

                /** @noinspection PhpUnused - Invoked via container resolution */
                public function handle(object $event): void
                {
                    $this->capture->called = true;
                    $this->capture->event = $event;
                }
            };

            $event = new stdClass();
            $event->payload = 'worker-injects-container';

            $envelope = createWorkerTestEnvelope();
            $container = createWorkerStubContainer($observer);

            $job = new AsyncObserverJob(
                observerClass: $observer::class,
                eventData: $envelope->wrap(serialize($event)),
            );
            $job->setId('async-job-1');

            $queue = new class ($job) implements QueueInterface
            {
                private bool $popped = false;

                public function __construct(
                    private readonly JobInterface $job,
                ) {}

                public function push(
                    JobInterface $job,
                    ?string $queue = null,
                ): PushResult {
                    return PushResult::pushed('async-job-1');
                }

                public function later(
                    int $delay,
                    JobInterface $job,
                    ?string $queue = null,
                ): PushResult {
                    return PushResult::pushed('async-job-1');
                }

                public function pop(
                    ?string $queue = null,
                    bool $block = false,
                ): ?JobInterface {
                    if ($this->popped) {
                        return null;
                    }
                    $this->popped = true;

                    return $this->job;
                }

                public function size(?string $queue = null): int
                {
                    return 0;
                }

                public function clear(?string $queue = null): int
                {
                    return 0;
                }

                public function delete(string $jobId): bool
                {
                    return true;
                }

                public function release(
                    JobInterface $job,
                    int $delay = 0,
                ): bool {
                    return true;
                }
            };

            $failedRepository = createTestFailedJobRepository();
            $config = createTestQueueConfig();

            $worker = new Worker(
                $queue,
                new FailedJobHandler(
                    $failedRepository,
                    $envelope,
                    new JobRunner($container, $envelope, new FakeEventDispatcher()),
                    new FakeEventDispatcher(),
                    new FakeClock(),
                ),
                $config,
                new JobRunner($container, $envelope, new FakeEventDispatcher()),
                new RetryPolicy($config, new FakeClock()),
                clock: new FakeClock(),
            );
            $worker->work(once: true);

            expect($capture->called)->toBeTrue()
                ->and($capture->event->payload)->toBe('worker-injects-container');
        },
    );
});

class ConfigDefaultFailingJob extends Job
{
    public function handle(): void
    {
        throw new RuntimeException('Always fails');
    }
}

class SingleJobRecordingQueue implements QueueInterface
{
    private bool $popped = false;

    /** @var list<int> */
    public array $releasedWithDelays = [];

    /** @var list<JobInterface> */
    public array $releasedJobs = [];

    public bool $deleted = false;

    public function __construct(
        private readonly JobInterface $job,
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('job-1');
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
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
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $this->releasedWithDelays[] = $delay;
        $this->releasedJobs[] = $job;

        return true;
    }
}

/**
 * Run one worker iteration for a failing job that has already used $priorAttempts attempts.
 *
 * @return array{queue: SingleJobRecordingQueue, failed: FailedJobRepositoryInterface}
 */
function runFailingJobOnce(
    JobInterface $job,
    int $priorAttempts,
    int $configMaxAttempts,
    FakeClock $clock = new FakeClock(),
): array {
    $job->setId('job-1');

    for ($i = 0; $i < $priorAttempts; $i++) {
        $job->incrementAttempts();
    }

    $queue = new SingleJobRecordingQueue($job);
    $failedRepository = createTestFailedJobRepository();

    $worker = new Worker(
        $queue,
        new FailedJobHandler(
            $failedRepository,
            createWorkerTestEnvelope(),
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            $clock,
        ),
        createTestQueueConfig(['queue.max_attempts' => $configMaxAttempts]),
        new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
        new RetryPolicy(createTestQueueConfig(['queue.max_attempts' => $configMaxAttempts]), new FakeClock()),
        clock: new FakeClock(),
    );
    $worker->work(once: true);

    return ['queue' => $queue, 'failed' => $failedRepository];
}

describe('Worker max attempts', function (): void {
    it(
        'releases a failing job while attempts are below queue.max_attempts when the job sets no maxAttempts',
        function (): void {
            $result = runFailingJobOnce(new ConfigDefaultFailingJob(), priorAttempts: 3, configMaxAttempts: 5);

            expect($result['queue']->releasedWithDelays)->toHaveCount(1)
                ->and($result['failed']->count())->toBe(0);
        },
    );

    it('fails a job once attempts reach queue.max_attempts when the job sets no maxAttempts', function (): void {
        $result = runFailingJobOnce(new ConfigDefaultFailingJob(), priorAttempts: 4, configMaxAttempts: 5);

        expect($result['queue']->releasedWithDelays)->toBe([])
            ->and($result['queue']->deleted)->toBeTrue()
            ->and($result['failed']->count())->toBe(1);
    });

    it('prefers the job maxAttempts over queue.max_attempts', function (): void {
        // FailingTestJob declares maxAttempts = 2 while config allows 10
        $result = runFailingJobOnce(new FailingTestJob(), priorAttempts: 1, configMaxAttempts: 10);

        expect($result['queue']->releasedWithDelays)->toBe([])
            ->and($result['failed']->count())->toBe(1);
    });

    it('records the failed job with the time from the injected clock', function (): void {
        $clock = new FakeClock('2026-03-01 09:15:00 UTC');

        $result = runFailingJobOnce(new FailingTestJob(), priorAttempts: 1, configMaxAttempts: 10, clock: $clock);

        expect($result['failed']->find('job-1')?->failedAt)->toEqual(new DateTimeImmutable('2026-03-01 09:15:00 UTC'));
    });
});

class BackoffFailingJob extends Job
{
    /**
     * @param int|list<int>|null $backoff
     */
    public function __construct(
        array|int|null $backoff = null,
    ) {
        $this->backoff = $backoff;
    }

    public function handle(): void
    {
        throw new RuntimeException('Always fails');
    }
}

function jobAfterAttempts(
    JobInterface $job,
    int $attempts,
): JobInterface {
    for ($i = 0; $i < $attempts; $i++) {
        $job->incrementAttempts();
    }

    return $job;
}

function createBackoffWorker(
    array $configValues = [],
): RetryPolicy {
    return new RetryPolicy(createTestQueueConfig($configValues), new FakeClock());
}

describe('Worker backoff (RetryPolicy::backoffFor)', function (): void {
    it('uses a fixed int job backoff for every attempt', function (): void {
        $worker = createBackoffWorker();

        expect($worker->backoffFor(jobAfterAttempts(new BackoffFailingJob(5), 1)))->toBe(5)
            ->and($worker->backoffFor(jobAfterAttempts(new BackoffFailingJob(5), 4)))->toBe(5);
    });

    it('uses the list job backoff per attempt and repeats the last value', function (): void {
        $worker = createBackoffWorker();
        $delays = array_map(
            fn (int $attempts): int => $worker->backoffFor(
                jobAfterAttempts(new BackoffFailingJob([5, 30, 120]), $attempts),
            ),
            [1, 2, 3, 4, 7],
        );

        expect($delays)->toBe([5, 30, 120, 120, 120]);
    });

    it('falls back to the queue.backoff config when the job sets none', function (): void {
        $intWorker = createBackoffWorker(['queue.backoff' => 15]);
        $listWorker = createBackoffWorker(['queue.backoff' => [10, 60]]);

        expect($intWorker->backoffFor(jobAfterAttempts(new BackoffFailingJob(), 2)))->toBe(15)
            ->and($listWorker->backoffFor(jobAfterAttempts(new BackoffFailingJob(), 1)))->toBe(10)
            ->and($listWorker->backoffFor(jobAfterAttempts(new BackoffFailingJob(), 3)))->toBe(60);
    });

    it('prefers the job backoff over the queue.backoff config', function (): void {
        $worker = createBackoffWorker(['queue.backoff' => 99]);

        expect($worker->backoffFor(jobAfterAttempts(new BackoffFailingJob(7), 1)))->toBe(7);
    });

    it('keeps the 2^attempts * 10 curve when neither job nor config sets backoff', function (): void {
        $worker = createBackoffWorker(['queue.backoff' => null]);
        $delays = array_map(
            fn (int $attempts): int => $worker->backoffFor(jobAfterAttempts(new BackoffFailingJob(), $attempts)),
            [1, 2, 3, 6],
        );

        expect($delays)->toBe([20, 40, 80, 640]);
    });

    it('throws QueueException for a negative or empty backoff', function (array|int $backoff): void {
        $worker = createBackoffWorker();

        expect(fn () => $worker->backoffFor(jobAfterAttempts(new BackoffFailingJob($backoff), 1)))
            ->toThrow(QueueException::class, 'Invalid queue backoff');
    })->with([
        'negative int' => [-1],
        'empty list' => [[[]]],
        'negative list entry' => [[[10, -5]]],
        'non-list array' => [[['a' => 10]]],
    ]);

    it('throws QueueException for an invalid queue.backoff config list', function (): void {
        $worker = createBackoffWorker(['queue.backoff' => [10, 'soon']]);

        expect(fn () => $worker->backoffFor(jobAfterAttempts(new BackoffFailingJob(), 1)))
            ->toThrow(QueueException::class, 'Invalid queue backoff in config queue.backoff');
    });

    it('releases a failed job with the backoffFor delay', function (): void {
        $result = runFailingJobOnce(new BackoffFailingJob([3, 9]), priorAttempts: 1, configMaxAttempts: 5);

        // Attempt 2 fails, so the second list entry applies
        expect($result['queue']->releasedWithDelays)->toBe([9]);
    });
});

class RecordingLog
{
    /** @var list<string> */
    public array $entries = [];
}

class NamedRecordingJob extends Job
{
    public function __construct(
        public string $name,
        public RecordingLog $log,
        public bool $fails = false,
    ) {}

    public function handle(): void
    {
        $this->log->entries[] = "handle:$this->name";

        if ($this->fails) {
            throw new RuntimeException("$this->name failed");
        }
    }
}

class MultiQueueRecordingQueue implements QueueInterface
{
    /**
     * @param array<string, list<JobInterface>> $jobs
     */
    public function __construct(
        public array $jobs,
        private readonly RecordingLog $log,
    ) {}

    /** @var list<array{queue: ?string, block: bool}> */
    public array $popCalls = [];

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        $this->jobs[$queue ?? 'default'][] = $job;

        return PushResult::pushed('pushed');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('later');
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        $this->log->entries[] = 'pop:' . ($queue ?? 'null');
        $this->popCalls[] = ['queue' => $queue, 'block' => $block];
        $name = $queue ?? 'default';

        if (($this->jobs[$name] ?? []) === []) {
            return null;
        }

        return array_shift($this->jobs[$name]);
    }

    public function size(
        ?string $queue = null,
    ): int {
        return count($this->jobs[$queue ?? 'default'] ?? []);
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

/**
 * Records each pause instead of sleeping, and stops the worker after the given number of pauses.
 */
class PauseRecordingWorker extends Worker
{
    private int $pauses = 0;

    public function __construct(
        QueueInterface $queue,
        FailedJobRepositoryInterface $failedJobRepository,
        QueueConfig $config,
        private readonly RecordingLog $log,
        private readonly int $stopAfterPauses = 1,
    ) {
        $runner = new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher());

        parent::__construct(
            $queue,
            new FailedJobHandler(
                $failedJobRepository,
                createWorkerTestEnvelope(),
                $runner,
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            $runner,
            new RetryPolicy($config, new FakeClock()),
            clock: new FakeClock(),
        );
    }

    protected function pause(
        int $seconds,
    ): void {
        $this->log->entries[] = "sleep:$seconds";
        $this->pauses++;

        if ($this->pauses >= $this->stopAfterPauses) {
            $this->stop();
        }
    }
}

function namedJob(
    string $name,
    RecordingLog $log,
    bool $fails = false,
): NamedRecordingJob {
    $job = new NamedRecordingJob($name, $log, $fails);
    $job->setId($name);

    return $job;
}

function createPauseRecordingWorker(
    MultiQueueRecordingQueue $queue,
    RecordingLog $log,
    ?FailedJobRepositoryInterface $failed = null,
    array $configValues = [],
): PauseRecordingWorker {
    return new PauseRecordingWorker(
        $queue,
        $failed ?? createTestFailedJobRepository(),
        createTestQueueConfig($configValues),
        $log,
    );
}

describe('Worker blocking pop', function (): void {
    it('lets the driver block on the only queue it works', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['emails' => []], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['emails'], once: true);

        expect($queue->popCalls)->toBe([['queue' => 'emails', 'block' => true]]);
    });

    it('lets the driver block on the default queue when no queues are given', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['default' => []], $log);

        createPauseRecordingWorker($queue, $log)->work(once: true);

        expect($queue->popCalls)->toBe([['queue' => null, 'block' => true]]);
    });

    it('never lets the driver block when it works several queues', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['high' => [], 'default' => [], 'low' => []], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'default', 'low'], once: true);

        expect($queue->popCalls)->toBe([
            ['queue' => 'high', 'block' => false],
            ['queue' => 'default', 'block' => false],
            ['queue' => 'low', 'block' => false],
        ]);
    });
});

describe('Worker priority queues', function (): void {
    it('processes jobs on the high queue before jobs on the low queue', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue([
            'low' => [namedJob('low-1', $log)],
            'high' => [namedJob('high-1', $log), namedJob('high-2', $log)],
        ], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'low'], sleep: 0);

        $handled = array_values(array_filter(
            $log->entries,
            fn (string $entry): bool => str_starts_with($entry, 'handle:'),
        ));

        expect($handled)->toBe(['handle:high-1', 'handle:high-2', 'handle:low-1']);
    });

    it('returns to the highest priority queue after each processed job', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue([
            'high' => [],
            'low' => [namedJob('low-1', $log)],
        ], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'low'], sleep: 0);

        expect($log->entries)->toBe([
            'pop:high', 'pop:low', 'handle:low-1',
            'pop:high', 'pop:low', 'sleep:0',
        ]);
    });

    it('sleeps only when every listed queue is empty', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue([
            'high' => [],
            'default' => [namedJob('default-1', $log)],
            'low' => [],
        ], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'default', 'low'], sleep: 5);

        expect($log->entries)->toBe([
            'pop:high', 'pop:default', 'handle:default-1',
            'pop:high', 'pop:default', 'pop:low', 'sleep:5',
        ]);
    });

    it('processes at most one job across all queues with once', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue([
            'high' => [namedJob('high-1', $log)],
            'low' => [namedJob('low-1', $log)],
        ], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'low'], once: true);

        expect($log->entries)->toBe(['pop:high', 'handle:high-1'])
            ->and($queue->jobs['low'])->toHaveCount(1);
    });

    it('returns without sleeping with once when every queue is empty', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['high' => [], 'low' => []], $log);

        createPauseRecordingWorker($queue, $log)->work(queues: ['high', 'low'], once: true);

        expect($log->entries)->toBe(['pop:high', 'pop:low']);
    });

    it('records the concrete queue a failed job was popped from', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue([
            'high' => [],
            'low' => [namedJob('low-fail', $log, fails: true)],
        ], $log);
        $failed = createTestFailedJobRepository();

        createPauseRecordingWorker($queue, $log, $failed, ['queue.max_attempts' => 1])
            ->work(queues: ['high', 'low'], once: true);

        expect($failed->find('low-fail')?->queue)->toBe('low');
    });

    it('records the config default queue when no queues are given', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['default' => [namedJob('default-fail', $log, fails: true)]], $log);
        $failed = createTestFailedJobRepository();

        createPauseRecordingWorker($queue, $log, $failed, ['queue.max_attempts' => 1, 'queue.queue' => 'emails'])
            ->work(once: true);

        expect($failed->find('default-fail')?->queue)->toBe('emails');
    });

    it('pops the driver default queue when queues is null', function (): void {
        $log = new RecordingLog();
        $queue = new MultiQueueRecordingQueue(['default' => []], $log);

        createPauseRecordingWorker($queue, $log)->work(sleep: 0);

        expect($log->entries)->toBe(['pop:null', 'sleep:0']);
    });

    it('throws QueueException when given an empty queue list', function (): void {
        $log = new RecordingLog();
        $worker = createPauseRecordingWorker(new MultiQueueRecordingQueue([], $log), $log);

        expect(fn () => $worker->work(queues: [], once: true))
            ->toThrow(QueueException::class, 'No queues given');
    });

    it('throws QueueException when a queue name is blank or not a string', function (array $queues): void {
        $log = new RecordingLog();
        $worker = createPauseRecordingWorker(new MultiQueueRecordingQueue([], $log), $log);

        expect(fn () => $worker->work(queues: $queues, once: true))
            ->toThrow(QueueException::class, 'Invalid queue name');
    })->with([
        'blank' => [['high', ' ']],
        'not a string' => [['high', 5]],
        'not a list' => [['first' => 'high']],
    ]);
});

/**
 * Pops the given jobs in order and records what the worker did with each one.
 */
class SequenceRecordingQueue implements QueueInterface
{
    /** @var list<string> */
    public array $deleted = [];

    /** @var list<string> */
    public array $released = [];

    /**
     * @param list<JobInterface> $jobs
     */
    public function __construct(
        private array $jobs,
    ) {}

    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('unused');
    }

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): PushResult {
        return PushResult::pushed('unused');
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
        return 0;
    }

    public function delete(
        string $jobId,
    ): bool {
        $this->deleted[] = $jobId;

        return true;
    }

    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        $this->released[] = $job->id;

        return true;
    }
}

/**
 * Container-aware job that keeps the injected container until releaseContainer() runs.
 */
class RecordingContainerAwareJob extends Job implements ContainerAwareJobInterface
{
    public private(set) ?ContainerInterface $container = null;

    public private(set) ?JobEnvelope $jobEnvelope = null;

    public private(set) int $releaseCount = 0;

    public private(set) bool $hadContainerWhenHandled = false;

    public function __construct(
        public readonly bool $fails = false,
    ) {}

    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    public function setJobEnvelope(JobEnvelope $jobEnvelope): void
    {
        $this->jobEnvelope = $jobEnvelope;
    }

    public function releaseContainer(): void
    {
        $this->container = null;
        $this->jobEnvelope = null;
        $this->releaseCount++;
    }

    public function handle(): void
    {
        $this->hadContainerWhenHandled = $this->container !== null && $this->jobEnvelope !== null;

        if ($this->fails) {
            throw new RuntimeException('Container-aware job always fails');
        }
    }
}

/**
 * Job whose payload can never be serialized: it holds a closure.
 */
class ClosureHoldingFailingJob extends Job
{
    public function __construct(
        public readonly Closure $callback,
    ) {}

    public function handle(): void
    {
        throw new RuntimeException('Closure job failed');
    }
}

/**
 * Stops the worker when it runs, proving the worker survived every job before it.
 */
class StopWorkerJob extends Job
{
    public ?Worker $worker = null;

    public function handle(): void
    {
        $this->worker?->stop();
    }
}

/**
 * Run the worker over the jobs until the trailing stop job runs.
 *
 * Every job is put on its last attempt first (queue.max_attempts is 3).
 *
 * @param list<JobInterface> $jobs
 * @return array{queue: SequenceRecordingQueue, failed: FailedJobRepositoryInterface}
 */
function runJobsOnLastAttempt(
    array $jobs,
): array {
    foreach ($jobs as $index => $job) {
        $job->setId("job-$index");
        $job->incrementAttempts();
        $job->incrementAttempts();
    }

    $stopJob = new StopWorkerJob();
    $stopJob->setId('stop-worker');

    $queue = new SequenceRecordingQueue([...$jobs, $stopJob]);
    $failedRepository = createTestFailedJobRepository();

    $worker = new Worker(
        $queue,
        new FailedJobHandler(
            $failedRepository,
            createWorkerTestEnvelope(),
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            new FakeClock(),
        ),
        createTestQueueConfig(),
        new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
        new RetryPolicy(createTestQueueConfig(), new FakeClock()),
        clock: new FakeClock(),
    );
    $stopJob->worker = $worker;

    $worker->work();

    return ['queue' => $queue, 'failed' => $failedRepository];
}

describe('Worker failed job serialization', function (): void {
    it(
        'stores a container-aware job that always fails in the failed-job repository once it reaches maxAttempts',
        function (): void {
            $result = runJobsOnLastAttempt([new RecordingContainerAwareJob(fails: true)]);

            $failedJob = $result['failed']->find('job-0');
            $stored = unserialize(createWorkerTestEnvelope()->verifyAndUnwrap($failedJob->payload));

            expect($failedJob->exception)->toContain('Container-aware job always fails')
                ->and($stored)->toBeInstanceOf(RecordingContainerAwareJob::class)
                ->and($stored->container)->toBeNull();
        },
    );

    it('keeps working after a container-aware job fails for the last time', function (): void {
        $result = runJobsOnLastAttempt([new RecordingContainerAwareJob(fails: true)]);

        expect($result['queue']->deleted)->toBe(['job-0', 'stop-worker'])
            ->and($result['queue']->released)->toBe([]);
    });

    it('releases the container from a container-aware job after handle succeeds', function (): void {
        $job = new RecordingContainerAwareJob();

        runJobsOnLastAttempt([$job]);

        expect($job->hadContainerWhenHandled)->toBeTrue()
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull();
    });

    it('releases the container from a container-aware job after handle throws', function (): void {
        $job = new RecordingContainerAwareJob(fails: true);

        runJobsOnLastAttempt([$job]);

        expect($job->hadContainerWhenHandled)->toBeTrue()
            ->and($job->releaseCount)->toBe(1)
            ->and($job->container)->toBeNull();
    });

    it('records a job whose payload cannot be serialized as failed with the serialization error', function (): void {
        $result = runJobsOnLastAttempt([new ClosureHoldingFailingJob(fn (): null => null)]);

        $failedJob = $result['failed']->find('job-0');

        expect($failedJob)->not->toBeNull()
            ->and($failedJob->exception)->toContain('Closure job failed')
            ->and($failedJob->exception)->toContain('Job payload could not be serialized')
            ->and($failedJob->exception)->toContain(ClosureHoldingFailingJob::class)
            ->and($failedJob->exception)->toContain("Serialization of 'Closure' is not allowed");
    });

    it('stores the job class in the payload of a job that cannot be serialized', function (): void {
        $result = runJobsOnLastAttempt([new ClosureHoldingFailingJob(fn (): null => null)]);

        $payload = unserialize(
            createWorkerTestEnvelope()->verifyAndUnwrap($result['failed']->find('job-0')->payload),
        );

        expect($payload)->toBe([
            'class' => ClosureHoldingFailingJob::class,
            'serialization_error' => "Serialization of 'Closure' is not allowed",
        ]);
    });

    it('deletes a job that cannot be serialized from the queue and keeps working', function (): void {
        $result = runJobsOnLastAttempt([
            new ClosureHoldingFailingJob(fn (): null => null),
            new RecordingContainerAwareJob(fails: true),
        ]);

        expect($result['queue']->deleted)->toBe(['job-0', 'job-1', 'stop-worker'])
            ->and($result['failed']->count())->toBe(2);
    });
});

/**
 * Run the worker over the jobs until the trailing stop job runs.
 *
 * Every job is on its first attempt, so a failing job has attempts left (queue.max_attempts is 3).
 *
 * @param list<JobInterface> $jobs
 * @param array<string, mixed> $configValues
 * @return array{queue: SequenceRecordingQueue, failed: FailedJobRepositoryInterface}
 */
function runJobsOnFirstAttempt(
    array $jobs,
    array $configValues = [],
    ?SequenceRecordingQueue $queue = null,
): array {
    foreach ($jobs as $index => $job) {
        $job->setId("job-$index");
    }

    $stopJob = new StopWorkerJob();
    $stopJob->setId('stop-worker');

    $queue ??= new SequenceRecordingQueue([...$jobs, $stopJob]);
    $failedRepository = createTestFailedJobRepository();

    $worker = new Worker(
        $queue,
        new FailedJobHandler(
            $failedRepository,
            createWorkerTestEnvelope(),
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new FakeEventDispatcher(),
            new FakeClock(),
        ),
        createTestQueueConfig($configValues),
        new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
        new RetryPolicy(createTestQueueConfig($configValues), new FakeClock()),
        clock: new FakeClock(),
    );
    $stopJob->worker = $worker;

    $worker->work();

    return ['queue' => $queue, 'failed' => $failedRepository];
}

/**
 * Job with an invalid backoff whose payload can never be serialized: it holds a closure.
 */
class ClosureHoldingInvalidBackoffJob extends Job
{
    public function __construct(
        public readonly Closure $callback,
    ) {
        $this->backoff = -1;
    }

    public function handle(): void
    {
        throw new RuntimeException('Closure job with bad backoff failed');
    }
}

/**
 * Queue whose release() fails, as a broken driver would.
 */
class FailingReleaseQueue extends SequenceRecordingQueue
{
    public function release(
        JobInterface $job,
        int $delay = 0,
    ): bool {
        throw new QueueException('Driver could not release the job.');
    }
}

describe('Worker invalid backoff', function (): void {
    it(
        'stores a job with an invalid backoff in failed jobs with the job error and the backoff error',
        function (): void {
            $result = runJobsOnFirstAttempt([new BackoffFailingJob(-5)]);

            $failedJob = $result['failed']->find('job-0');

            expect($failedJob)->not->toBeNull()
                ->and($failedJob->exception)->toContain('Always fails')
                ->and($failedJob->exception)->toContain(
                    'Invalid queue backoff in job ' . BackoffFailingJob::class . '.',
                )
                ->and($failedJob->exception)->toContain('delay must not be negative; got -5');
        },
    );

    it('deletes a job with an invalid backoff from the queue instead of releasing it', function (): void {
        $result = runJobsOnFirstAttempt([new BackoffFailingJob([])]);

        expect($result['queue']->released)->toBe([])
            ->and($result['queue']->deleted)->toContain('job-0');
    });

    it('processes the next job after failing a job with an invalid backoff', function (): void {
        $result = runJobsOnFirstAttempt([new BackoffFailingJob(['a' => 10]), new BackoffFailingJob(5)]);

        expect($result['failed']->count())->toBe(1)
            ->and($result['queue']->released)->toBe(['job-1'])
            ->and($result['queue']->deleted)->toBe(['job-0', 'stop-worker']);
    });

    it('stores a retryable copy of the job that had an invalid backoff', function (): void {
        $result = runJobsOnFirstAttempt([new BackoffFailingJob(-5)]);

        $stored = unserialize(
            createWorkerTestEnvelope()->verifyAndUnwrap($result['failed']->find('job-0')->payload),
        );

        expect($stored)->toBeInstanceOf(BackoffFailingJob::class)
            ->and($stored->backoff)->toBe(-5);
    });

    it('fails the job when the queue.backoff config is invalid instead of stopping the worker', function (): void {
        $result = runJobsOnFirstAttempt([new BackoffFailingJob()], ['queue.backoff' => [10, 'soon']]);

        $failedJob = $result['failed']->find('job-0');

        expect($failedJob)->not->toBeNull()
            ->and($failedJob->exception)->toContain('Always fails')
            ->and($failedJob->exception)->toContain('Invalid queue backoff in config queue.backoff.')
            ->and($result['queue']->deleted)->toBe(['job-0', 'stop-worker']);
    });

    it(
        'records the job error, the backoff error and the serialization note when an invalid-backoff job cannot be serialized',
        function (): void {
            $result = runJobsOnFirstAttempt([new ClosureHoldingInvalidBackoffJob(fn (): null => null)]);

            $failedJob = $result['failed']->find('job-0');

            expect($failedJob->exception)->toContain('Closure job with bad backoff failed')
                ->and($failedJob->exception)->toContain('Invalid queue backoff in job')
                ->and($failedJob->exception)->toContain('Job payload could not be serialized');
        },
    );

    it("still propagates a QueueException thrown by the driver's release()", function (): void {
        $job = new BackoffFailingJob(5);
        $job->setId('job-0');
        $queue = new FailingReleaseQueue([$job]);

        expect(fn () => runJobsOnFirstAttempt([], queue: $queue))
            ->toThrow(QueueException::class, 'Driver could not release the job.');
    });
});

/**
 * Throws from pop() for the first $failures calls, then pops jobs like MultiQueueRecordingQueue.
 */
class FailingPopQueue extends MultiQueueRecordingQueue
{
    private int $pops = 0;

    /**
     * @param array<string, list<JobInterface>> $jobs
     */
    public function __construct(
        array $jobs,
        RecordingLog $log,
        private readonly int $failures = 1,
    ) {
        parent::__construct($jobs, $log);
    }

    public function pop(
        ?string $queue = null,
        bool $block = false,
    ): ?JobInterface {
        if ($this->pops++ < $this->failures) {
            throw new RuntimeException('Broker connection lost');
        }

        return parent::pop($queue, $block);
    }
}

/**
 * Point PHP's error log at a temp file for the callback and return what was logged.
 */
function captureWorkerErrorLog(
    callable $callback,
): string {
    $file = tempnam(sys_get_temp_dir(), 'marko-worker-log');
    $previous = ini_set('error_log', $file);

    try {
        $callback();
    } finally {
        ini_set('error_log', (string) $previous);
    }

    $logged = (string) file_get_contents($file);
    unlink($file);

    return $logged;
}

describe('Worker pop failures', function (): void {
    it('keeps running when pop() throws, then processes the next job', function (): void {
        $log = new RecordingLog();
        $queue = new FailingPopQueue(['default' => [namedJob('after-failure', $log)]], $log);
        $worker = new PauseRecordingWorker(
            $queue,
            createTestFailedJobRepository(),
            createTestQueueConfig(),
            $log,
            stopAfterPauses: 2,
        );

        captureWorkerErrorLog(fn () => $worker->work(sleep: 7));

        expect($log->entries)->toBe([
            'sleep:7',
            'pop:null',
            'handle:after-failure',
            'pop:null',
            'sleep:7',
        ]);
    });

    it('logs the pop failure with the exception class and message', function (): void {
        $log = new RecordingLog();
        $worker = new PauseRecordingWorker(
            new FailingPopQueue([], $log),
            createTestFailedJobRepository(),
            createTestQueueConfig(),
            $log,
        );

        $logged = captureWorkerErrorLog(fn () => $worker->work());

        expect($logged)->toContain('Worker could not pop a job')
            ->and($logged)->toContain('RuntimeException: Broker connection lost');
    });

    it('rethrows a pop failure in --once mode instead of reporting success', function (): void {
        $log = new RecordingLog();
        $worker = new PauseRecordingWorker(
            new FailingPopQueue([], $log),
            createTestFailedJobRepository(),
            createTestQueueConfig(),
            $log,
        );

        expect(fn () => $worker->work(once: true))->toThrow(RuntimeException::class, 'Broker connection lost');
    });
});

/**
 * JobRunner double that records each job it runs and fails or succeeds as told.
 */
class RecordingJobRunner implements JobRunnerInterface
{
    /** @var list<JobInterface> */
    public array $ran = [];

    public function __construct(
        private readonly bool $fails = false,
    ) {}

    public function run(JobInterface $job): void
    {
        $this->ran[] = $job;

        if ($this->fails) {
            throw new RuntimeException('runner failed the job');
        }
    }

    public function withContainer(
        JobInterface $job,
        callable $callback,
    ): mixed {
        return $callback();
    }
}

describe('Worker and JobRunner', function (): void {
    it('runs each popped job through the JobRunner it is given', function (): void {
        $job = new ConfigDefaultFailingJob();
        $job->setId('job-1');
        $queue = new SingleJobRecordingQueue($job);
        $runner = new RecordingJobRunner();

        new Worker(
            $queue,
            new FailedJobHandler(
                createTestFailedJobRepository(),
                createWorkerTestEnvelope(),
                $runner,
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            createTestQueueConfig(),
            $runner,
            new RetryPolicy(createTestQueueConfig(), new FakeClock()),
            clock: new FakeClock(),
        )->work(once: true);

        expect($runner->ran)->toBe([$job])
            ->and($queue->deleted)->toBeTrue();
    });

    it('hands the in-memory job, with its counted attempt and exception, to release()', function (): void {
        $job = new ConfigDefaultFailingJob();
        $job->setId('job-1');
        $queue = new SingleJobRecordingQueue($job);

        new Worker(
            $queue,
            new FailedJobHandler(
                createTestFailedJobRepository(),
                createWorkerTestEnvelope(),
                new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            createTestQueueConfig(['queue.max_attempts' => 3]),
            new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher()),
            new RetryPolicy(createTestQueueConfig(['queue.max_attempts' => 3]), new FakeClock()),
            clock: new FakeClock(),
        )->work(once: true);

        expect($queue->releasedJobs)->toBe([$job])
            ->and($queue->releasedJobs[0]->attempts)->toBe(1)
            ->and($queue->releasedJobs[0]->exceptions)->toBe(1);
    });
});

class FailableFailingJob extends Job implements FailableJobInterface
{
    /** @var list<Throwable> */
    public array $failedWith = [];

    public function __construct(
        public protected(set) array|int|null $backoff = null,
        private readonly bool $failedThrows = false,
    ) {}

    public function handle(): void
    {
        throw new RuntimeException('failable job failed');
    }

    public function failed(Throwable $exception): void
    {
        $this->failedWith[] = $exception;

        if ($this->failedThrows) {
            throw new LogicException('cleanup broke');
        }
    }
}

/**
 * Run the worker over the jobs (each with the given prior attempts) until the trailing stop job runs.
 *
 * @param list<JobInterface> $jobs
 * @return array{queue: SequenceRecordingQueue, events: FakeEventDispatcher}
 */
function runFailableJobs(
    array $jobs,
    int $priorAttempts,
    int $maxAttempts = 3,
): array {
    foreach ($jobs as $index => $job) {
        $job->setId("job-$index");

        for ($i = 0; $i < $priorAttempts; $i++) {
            $job->incrementAttempts();
        }
    }

    $stopJob = new StopWorkerJob();
    $stopJob->setId('stop-worker');
    $queue = new SequenceRecordingQueue([...$jobs, $stopJob]);
    $events = new FakeEventDispatcher();
    $runner = new JobRunner(createNullWorkerContainer(), createWorkerTestEnvelope(), new FakeEventDispatcher());

    $worker = new Worker(
        $queue,
        new FailedJobHandler(
            createTestFailedJobRepository(),
            createWorkerTestEnvelope(),
            $runner,
            $events,
            new FakeClock(),
        ),
        createTestQueueConfig(['queue.max_attempts' => $maxAttempts]),
        $runner,
        new RetryPolicy(createTestQueueConfig(['queue.max_attempts' => $maxAttempts]), new FakeClock()),
        clock: new FakeClock(),
    );
    $stopJob->worker = $worker;

    $worker->work();

    return ['queue' => $queue, 'events' => $events];
}

describe('Worker failure hook', function (): void {
    it('does not call failed() on an attempt that is released for a retry', function (): void {
        $job = new FailableFailingJob();

        ['queue' => $queue, 'events' => $events] = runFailableJobs([$job], priorAttempts: 0);

        expect($job->failedWith)->toBe([])
            ->and($queue->released)->toBe(['job-0'])
            ->and($events->dispatched(JobFailedEvent::class))->toBe([]);
    });

    it('calls failed() with the exception on the last attempt, then deletes the job', function (): void {
        $job = new FailableFailingJob();

        ['queue' => $queue] = runFailableJobs([$job], priorAttempts: 2);

        expect($job->failedWith)->toHaveCount(1)
            ->and($job->failedWith[0]->getMessage())->toBe('failable job failed')
            ->and($queue->deleted)->toContain('job-0');
    });

    it('dispatches JobFailedEvent with the exception and the queue on the last attempt', function (): void {
        $job = new FailableFailingJob();

        ['events' => $events] = runFailableJobs([$job], priorAttempts: 2);

        $failed = $events->dispatched(JobFailedEvent::class);

        expect($failed)->toHaveCount(1)
            ->and($failed[0]->job)->toBe($job)
            ->and($failed[0]->queue)->toBe('default')
            ->and($failed[0]->exception->getMessage())->toBe('failable job failed');
    });

    it('calls failed() when the job cannot be retried because its backoff is invalid', function (): void {
        $job = new FailableFailingJob(backoff: -5);

        ['queue' => $queue] = runFailableJobs([$job], priorAttempts: 0);

        expect($job->failedWith)->toHaveCount(1)
            ->and($queue->released)->toBe([])
            ->and($queue->deleted)->toContain('job-0');
    });

    it('logs a throwing failed(), still dispatches JobFailedEvent, and keeps processing jobs', function (): void {
        $broken = new FailableFailingJob(failedThrows: true);
        $next = new FailableFailingJob();
        $result = [];

        $logged = captureWorkerErrorLog(function () use ($broken, $next, &$result): void {
            $result = runFailableJobs([$broken, $next], priorAttempts: 2);
        });

        expect($logged)->toContain('FailableFailingJob')
            ->and($logged)->toContain('job-0')
            ->and($logged)->toContain('cleanup broke')
            ->and($result['events']->dispatched(JobFailedEvent::class))->toHaveCount(2)
            ->and($next->failedWith)->toHaveCount(1)
            ->and($result['queue']->deleted)->toBe(['job-0', 'job-1', 'stop-worker']);
    });
});
