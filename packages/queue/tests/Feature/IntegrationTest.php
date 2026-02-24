<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\Command\WorkCommand;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * Create a test queue config.
 *
 * @param array<string, mixed> $values
 */
function createIntegrationQueueConfig(
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

/**
 * Create a test failed job repository that stores in memory.
 */
function createIntegrationFailedJobRepository(): FailedJobRepositoryInterface
{
    return new class () implements FailedJobRepositoryInterface
    {
        /** @var array<string, FailedJob> */
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

/**
 * Create an in-memory queue implementation for integration testing.
 */
function createInMemoryQueue(): QueueInterface
{
    return new class () implements QueueInterface
    {
        /** @var array<string, array{job: JobInterface, queue: string, availableAt: int}> */
        private array $jobs = [];

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            $id = bin2hex(random_bytes(16));
            $job->setId($id);

            $this->jobs[$id] = [
                'job' => $job,
                'queue' => $queue ?? 'default',
                'availableAt' => time(),
            ];

            return $id;
        }

        public function later(
            int $delay,
            JobInterface $job,
            ?string $queue = null,
        ): string {
            $id = bin2hex(random_bytes(16));
            $job->setId($id);

            $this->jobs[$id] = [
                'job' => $job,
                'queue' => $queue ?? 'default',
                'availableAt' => time() + $delay,
            ];

            return $id;
        }

        public function pop(
            ?string $queue = null,
        ): ?JobInterface {
            $queueName = $queue ?? 'default';
            $now = time();

            foreach ($this->jobs as $id => $data) {
                if ($data['queue'] === $queueName && $data['availableAt'] <= $now) {
                    unset($this->jobs[$id]);

                    return $data['job'];
                }
            }

            return null;
        }

        public function size(
            ?string $queue = null,
        ): int {
            $queueName = $queue ?? 'default';
            $now = time();
            $count = 0;

            foreach ($this->jobs as $data) {
                if ($data['queue'] === $queueName && $data['availableAt'] <= $now) {
                    $count++;
                }
            }

            return $count;
        }

        public function clear(
            ?string $queue = null,
        ): int {
            $queueName = $queue ?? 'default';
            $count = 0;

            foreach ($this->jobs as $id => $data) {
                if ($data['queue'] === $queueName) {
                    unset($this->jobs[$id]);
                    $count++;
                }
            }

            return $count;
        }

        public function delete(
            string $jobId,
        ): bool {
            if (isset($this->jobs[$jobId])) {
                unset($this->jobs[$jobId]);

                return true;
            }

            return false;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            // For released jobs, we need to find them in our storage
            // Since pop removes them, we need a different approach
            return true;
        }
    };
}

describe('Integration Tests', function (): void {
    test('Job lifecycle from push to completion', function (): void {
        // Track whether the job was handled
        $capture = (object) ['handled' => false, 'message' => null];

        // Create a real job that tracks its execution
        $job = new class ('Hello, World!', $capture) extends Job
        {
            public function __construct(
                private readonly string $message,
                private object $capture,
            ) {}

            public function handle(): void
            {
                $this->capture->handled = true;
                $this->capture->message = $this->message;
            }
        };

        // Create the queue, repository, config, and worker
        $queue = createInMemoryQueue();
        $failedRepository = createIntegrationFailedJobRepository();
        $config = createIntegrationQueueConfig();
        $worker = new Worker($queue, $failedRepository, $config);

        // 1. Push the job to the queue
        $jobId = $queue->push($job);

        expect($jobId)->toBeString()
            ->and($job->id)->toBe($jobId)
            ->and($queue->size())->toBe(1);

        // 2. Verify job is in the queue

        // 3. Process the job via worker
        $worker->work(once: true);

        // 4. Verify job was handled
        expect($capture->handled)->toBeTrue()
            ->and($capture->message)->toBe('Hello, World!')
            ->and($queue->size())->toBe(0)
            ->and($failedRepository->count())->toBe(0);

        // 5. Verify queue is now empty

        // 6. Verify no failed jobs
    });

    test('Async observer queues and executes', function (): void {
        // Track observer execution
        $capture = (object) ['called' => false, 'event' => null];

        // Create mock observer
        $observer = new class ($capture)
        {
            public function __construct(
                private object $capture,
            ) {}

            public function handle(
                object $event,
            ): void {
                $this->capture->called = true;
                $this->capture->event = $event;
            }
        };

        // Create event
        $event = new stdClass();
        $event->type = 'user.created';
        $event->userId = 123;

        // Create AsyncObserverJob
        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: serialize($event),
        );

        // Create queue and push the job
        $queue = createInMemoryQueue();

        $jobId = $queue->push($job);

        expect($jobId)->toBeString()
            ->and($queue->size())->toBe(1);

        // Pop the job from the queue
        $poppedJob = $queue->pop();

        expect($poppedJob)->toBeInstanceOf(AsyncObserverJob::class);

        // Execute the job with a resolver that returns our observer
        $poppedJob->handle(fn (string $class): object => $observer);

        // Verify observer was called with the correct event
        expect($capture->called)->toBeTrue()
            ->and($capture->event)->toBeInstanceOf(stdClass::class)
            ->and($capture->event->type)->toBe('user.created')
            ->and($capture->event->userId)->toBe(123)
            ->and($queue->size())->toBe(0);

        // Verify queue is empty
    });

    test('CLI commands work with drivers', function (): void {
        // Track job execution
        $capture = (object) ['executed' => false];

        // Create a job that tracks execution
        $job = new class ($capture) extends Job
        {
            public function __construct(
                private object $capture,
            ) {}

            public function handle(): void
            {
                $this->capture->executed = true;
            }
        };

        // Set up components
        $queue = createInMemoryQueue();
        $failedRepository = createIntegrationFailedJobRepository();
        $config = createIntegrationQueueConfig();
        $worker = new Worker($queue, $failedRepository, $config);

        // Push a job to the queue
        $queue->push($job);

        expect($queue->size())->toBe(1);

        // Create WorkCommand with our worker
        $workCommand = new WorkCommand($worker);

        // Create output stream
        $stream = fopen('php://memory', 'r+');
        $output = new Output($stream);
        $input = new Input(['marko', 'queue:work', '--once']);

        // Execute the command
        $exitCode = $workCommand->execute($input, $output);

        // Verify command completed successfully
        expect($exitCode)->toBe(0)
            ->and($capture->executed)->toBeTrue()
            ->and($queue->size())->toBe(0);

        // Verify job was executed

        // Verify queue is now empty

        // Verify output contains expected message
        rewind($stream);
        $outputContent = stream_get_contents($stream);

        expect($outputContent)->toContain('Processing jobs from queue');
        fclose($stream);
    });

    test('Module bindings resolve correctly', function (): void {
        // Test that queue-sync module bindings are correct
        $syncModulePath = dirname(__DIR__, 3) . '/queue-sync/module.php';
        $syncModule = require $syncModulePath;

        expect($syncModule)->toBeArray()
            ->and($syncModule['bindings'])->toHaveKey(QueueInterface::class)
            ->and($syncModule['bindings'])->toHaveKey(FailedJobRepositoryInterface::class);

        // Verify the bindings can be instantiated
        $queueConfig = createIntegrationQueueConfig();

        expect($queueConfig)->toBeInstanceOf(QueueConfig::class)
            ->and($queueConfig->driver())->toBe('sync')
            ->and($queueConfig->queue())->toBe('default');

        // Verify SyncQueue implements QueueInterface
        $syncQueue = new SyncQueue();

        expect($syncQueue)->toBeInstanceOf(QueueInterface::class);

        // Verify NullFailedJobRepository implements FailedJobRepositoryInterface
        $nullRepository = new NullFailedJobRepository();

        expect($nullRepository)->toBeInstanceOf(FailedJobRepositoryInterface::class);

        // Verify Worker implements WorkerInterface
        $worker = new Worker($syncQueue, $nullRepository, $queueConfig);

        expect($worker)->toBeInstanceOf(WorkerInterface::class);

        // Test that the complete system can be wired together
        $capture = (object) ['handled' => false];

        $job = new class ($capture) extends Job
        {
            public function __construct(
                private object $capture,
            ) {}

            public function handle(): void
            {
                $this->capture->handled = true;
            }
        };

        // Push job through SyncQueue (executes immediately)
        $jobId = $syncQueue->push($job);

        expect($jobId)->toBeString()
            ->and($capture->handled)->toBeTrue(); // SyncQueue executes immediately
    });
});
