<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\JobRunner;
use Marko\Queue\PushResult;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Sending\WebhookDeliveryService;
use Marko\Webhook\Tests\Fixtures\FakeEncryptor;
use Marko\Webhook\Value\SealedWebhookPayload;
use Marko\Webhook\Value\WebhookPayload;
use Marko\Webhook\Value\WebhookResponse;
use RuntimeException;

/**
 * Shared test helpers for serializable webhook job tests.
 * These are static methods on a dedicated class to avoid PSR-4 autoload conflicts
 * that arise when defining namespace-level functions in discoverable test files.
 */
final class SerializableWebhookJobTestHelpers
{
    public static function payload(): WebhookPayload
    {
        return new WebhookPayload(
            url: 'https://example.com/hook',
            event: 'order.created',
            data: ['order_id' => 42],
            secret: 'test-signing-secret',
        );
    }

    public static function envelope(): JobEnvelope
    {
        return new JobEnvelope(
            new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'webhook-test-key'])),
        );
    }

    public static function queueConfig(): QueueConfig
    {
        return new QueueConfig(new FakeConfigRepository([
            'queue.driver' => 'sync',
            'queue.connection' => 'default',
            'queue.queue' => 'default',
            'queue.retry_after' => 90,
            'queue.max_attempts' => 3,
        ]));
    }

    public static function failedJobRepository(): FailedJobRepositoryInterface
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

            /** @return array<FailedJob> */
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

    public static function deliveryService(): WebhookDeliveryService
    {
        $stubRepo = new class () implements WebhookAttemptRepositoryInterface
        {
            public function save(
                WebhookAttempt $attempt,
            ): WebhookAttempt {
                return $attempt;
            }
        };

        return new WebhookDeliveryService($stubRepo, new FakeClock(), DatabaseTimezoneConfig::fromName('UTC'));
    }

    public static function container(
        WebhookDispatcherInterface $dispatcher,
        WebhookDeliveryService $deliveryService,
        ConfigRepositoryInterface $config,
        QueueInterface $queue,
    ): ContainerInterface {
        return new readonly class ($dispatcher, $deliveryService, $config, $queue) implements ContainerInterface
        {
            public function __construct(
                private WebhookDispatcherInterface $dispatcher,
                private WebhookDeliveryService $deliveryService,
                private ConfigRepositoryInterface $config,
                private QueueInterface $queue,
            ) {}

            public function get(
                string $id,
            ): object {
                return match ($id) {
                    EncryptorInterface::class => new FakeEncryptor(),
                    WebhookDispatcherInterface::class => $this->dispatcher,
                    WebhookDeliveryService::class => $this->deliveryService,
                    ConfigRepositoryInterface::class => $this->config,
                    QueueInterface::class => $this->queue,
                    default => throw new RuntimeException("No binding for: $id"),
                };
            }

            public function has(
                string $id,
            ): bool {
                return true;
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function call(
                Closure $callable,
            ): mixed {
                return null;
            }

            public function resolvedInstances(
                ?string $interface = null,
            ): array {
                return [];
            }
        };
    }

    /**
     * A container that cannot be serialized (anonymous class) and resolves nothing.
     */
    public static function unserializableFailingContainer(): ContainerInterface
    {
        return new class () implements ContainerInterface
        {
            public function get(
                string $id,
            ): never {
                throw new RuntimeException("Webhook dispatcher unavailable: $id");
            }

            public function has(
                string $id,
            ): bool {
                return false;
            }

            public function singleton(string $id): void {}

            public function instance(
                string $id,
                object $instance,
            ): void {}

            public function call(
                Closure $callable,
            ): mixed {
                return null;
            }

            public function resolvedInstances(
                ?string $interface = null,
            ): array {
                return [];
            }
        };
    }

    /**
     * Pops the given jobs in order and records which job IDs the worker deleted.
     *
     * @param list<JobInterface> $jobs
     */
    public static function sequenceQueue(
        array $jobs,
    ): QueueInterface {
        return new class ($jobs) implements QueueInterface
        {
            /** @var list<string> */
            public array $deleted = [];

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
                return true;
            }
        };
    }
}

describe('DispatchWebhookJob final failure', function (): void {
    it(
        'stores a DispatchWebhookJob that fails for the last time in the failed-job repository and keeps the worker running',
        function (): void {
            $job = new DispatchWebhookJob(
                SealedWebhookPayload::seal(SerializableWebhookJobTestHelpers::payload(), new FakeEncryptor()),
            );
            $job->setId('webhook-final-failure');
            // queue.max_attempts is 3: this run is the job's last attempt
            $job->incrementAttempts();
            $job->incrementAttempts();

            $stopJob = new class () extends Job
            {
                public ?Worker $worker = null;

                public function handle(): void
                {
                    $this->worker?->stop();
                }
            };
            $stopJob->setId('stop-worker');

            $queue = SerializableWebhookJobTestHelpers::sequenceQueue([$job, $stopJob]);
            $failedRepository = SerializableWebhookJobTestHelpers::failedJobRepository();

            $worker = new Worker(
                $queue,
                new FailedJobHandler(
                    $failedRepository,
                    SerializableWebhookJobTestHelpers::envelope(),
                    new JobRunner(
                        SerializableWebhookJobTestHelpers::unserializableFailingContainer(),
                        SerializableWebhookJobTestHelpers::envelope(),
                        new FakeEventDispatcher(),
                    ),
                    new FakeEventDispatcher(),
                    new FakeClock(),
                ),
                SerializableWebhookJobTestHelpers::queueConfig(),
                new JobRunner(
                    SerializableWebhookJobTestHelpers::unserializableFailingContainer(),
                    SerializableWebhookJobTestHelpers::envelope(),
                    new FakeEventDispatcher(),
                ),
                new RetryPolicy(SerializableWebhookJobTestHelpers::queueConfig(), new FakeClock()),
                clock: new FakeClock(),
            );
            $stopJob->worker = $worker;

            $worker->work();

            $failedJob = $failedRepository->find('webhook-final-failure');
            $envelope = SerializableWebhookJobTestHelpers::envelope();
            $stored = Job::unserialize($envelope->verifyAndUnwrap($failedJob->payload));

            expect($failedJob->exception)->toContain('Webhook dispatcher unavailable')
                ->and($stored)->toBeInstanceOf(DispatchWebhookJob::class)
                ->and($queue->deleted)->toBe(['webhook-final-failure', 'stop-worker']);
        },
    );

    it('releases the container from DispatchWebhookJob when releaseContainer is called', function (): void {
        $job = new DispatchWebhookJob(
            SealedWebhookPayload::seal(SerializableWebhookJobTestHelpers::payload(), new FakeEncryptor()),
        );
        $job->setContainer(SerializableWebhookJobTestHelpers::unserializableFailingContainer());

        $job->releaseContainer();

        expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'without a container');
    });
});

describe('DispatchWebhookJob serialization', function (): void {
    it('serializes and unserializes a DispatchWebhookJob without error', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()));

        $serialized = $job->serialize();
        $unserialized = Job::unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(DispatchWebhookJob::class);
    });

    it('dispatches the webhook payload when a unserialized DispatchWebhookJob is handled', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()));

        $serialized = $job->serialize();
        /** @var DispatchWebhookJob $unserialized */
        $unserialized = Job::unserialize($serialized);

        $dispatched = [];
        $dispatcher = new class ($dispatched) implements WebhookDispatcherInterface
        {
            /** @param array<WebhookPayload> $dispatched */
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property used to track dispatch */
                private array &$dispatched,
            ) {}

            public function dispatch(
                WebhookPayload $payload,
            ): WebhookResponse {
                $this->dispatched[] = $payload;

                return new WebhookResponse(200, 'OK', true);
            }
        };

        $container = SerializableWebhookJobTestHelpers::container(
            $dispatcher,
            SerializableWebhookJobTestHelpers::deliveryService(),
            new FakeConfigRepository(['webhook.max_retries' => 3, 'webhook.retry_delay' => 60]),
            new FakeQueue(),
        );

        $unserialized->setContainer($container);
        $unserialized->handle();

        expect($dispatched)->toHaveCount(1)
            ->and($dispatched[0])->toEqual($payload);
    });

    it(
        'never writes the signing secret into the serialized job or its retry, and keeps the delivery ID',
        function (): void {
            $payload = SerializableWebhookJobTestHelpers::payload();
            $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()));
            $queue = new FakeQueue();

            $failingDispatcher = new class () implements WebhookDispatcherInterface
            {
                public function dispatch(
                    WebhookPayload $payload,
                ): WebhookResponse {
                    throw new RuntimeException('Connection refused');
                }
            };

            $job->setContainer(SerializableWebhookJobTestHelpers::container(
                $failingDispatcher,
                SerializableWebhookJobTestHelpers::deliveryService(),
                new FakeConfigRepository(['webhook.max_retries' => 3, 'webhook.retry_delay' => 60]),
                $queue,
            ));
            $job->handle();
            $job->releaseContainer();

            /** @var DispatchWebhookJob $retry */
            $retry = $queue->pushed[0]['job'];

            expect($job->serialize())->not->toContain($payload->secret)
                ->and($retry->serialize())->not->toContain($payload->secret)
                ->and($retry->payload->id)->toBe($payload->id);
        },
    );

    it('re-enqueues a retry job that is itself serializable after a webhook failure', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()), attemptNumber: 1);

        $serialized = $job->serialize();
        /** @var DispatchWebhookJob $unserialized */
        $unserialized = Job::unserialize($serialized);

        $retryQueue = new FakeQueue();

        $dispatcher = new class () implements WebhookDispatcherInterface
        {
            public function dispatch(
                WebhookPayload $payload,
            ): WebhookResponse {
                throw new RuntimeException('Connection failed');
            }
        };

        $container = SerializableWebhookJobTestHelpers::container(
            $dispatcher,
            SerializableWebhookJobTestHelpers::deliveryService(),
            new FakeConfigRepository(['webhook.max_retries' => 3, 'webhook.retry_delay' => 60]),
            $retryQueue,
        );

        $unserialized->setContainer($container);
        $unserialized->handle();

        // A retry job should have been enqueued with a delay
        expect($retryQueue->pushed)->toHaveCount(1);

        $retryJob = $retryQueue->pushed[0]['job'];

        expect($retryJob)->toBeInstanceOf(DispatchWebhookJob::class);

        // The retry job must itself be serializable (no live services stored)
        $retrySerialized = $retryJob->serialize();
        $retryUnserialized = Job::unserialize($retrySerialized);

        expect($retryUnserialized)->toBeInstanceOf(DispatchWebhookJob::class);
    });

    it('holds only serializable data and no live service instances on either job', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()), attemptNumber: 2);

        // Serialization must succeed (no closures, PDO, or live service objects)
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(DispatchWebhookJob::class);
    });

    it(
        'receives the container from the Worker so a webhook or notification job resolves its services in the real Worker path',
        function (): void {
            $payload = SerializableWebhookJobTestHelpers::payload();
            $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()));
            $job->setId('webhook-job-1');

            $dispatched = [];
            $dispatcher = new class ($dispatched) implements WebhookDispatcherInterface
            {
                /** @param array<WebhookPayload> $dispatched */
                public function __construct(
                    /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property used to track dispatch */
                    private array &$dispatched,
                ) {}

                public function dispatch(
                    WebhookPayload $payload,
                ): WebhookResponse {
                    $this->dispatched[] = $payload;

                    return new WebhookResponse(200, 'OK', true);
                }
            };

            $fakeQueue = new FakeQueue();

            $workerQueue = new class ($job) implements QueueInterface
            {
                private bool $popped = false;

                public function __construct(
                    private readonly JobInterface $job,
                ) {}

                public function push(
                    JobInterface $job,
                    ?string $queue = null,
                ): PushResult {
                    return PushResult::pushed('webhook-job-1');
                }

                public function later(
                    int $delay,
                    JobInterface $job,
                    ?string $queue = null,
                ): PushResult {
                    return PushResult::pushed('webhook-job-1');
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
                    return true;
                }

                public function release(
                    JobInterface $job,
                    int $delay = 0,
                ): bool {
                    return true;
                }
            };

            $container = SerializableWebhookJobTestHelpers::container(
                $dispatcher,
                SerializableWebhookJobTestHelpers::deliveryService(),
                new FakeConfigRepository(['webhook.max_retries' => 3, 'webhook.retry_delay' => 60]),
                $fakeQueue,
            );

            $envelope = SerializableWebhookJobTestHelpers::envelope();
            $failedRepository = SerializableWebhookJobTestHelpers::failedJobRepository();
            $queueConfig = SerializableWebhookJobTestHelpers::queueConfig();

            // Drive through Worker::work() — NOT a manual setContainer() call
            $worker = new Worker(
                $workerQueue,
                new FailedJobHandler(
                    $failedRepository,
                    $envelope,
                    new JobRunner($container, $envelope, new FakeEventDispatcher()),
                    new FakeEventDispatcher(),
                    new FakeClock(),
                ),
                $queueConfig,
                new JobRunner($container, $envelope, new FakeEventDispatcher()),
                new RetryPolicy($queueConfig, new FakeClock()),
                clock: new FakeClock(),
            );
            $worker->work(once: true);

            // The dispatcher was called, proving Worker injected the container via the ContainerAwareJobInterface gate
            expect($dispatched)->toHaveCount(1)
                    ->and($dispatched[0])->toEqual($payload);
        },
    );

    it('re-enqueues a webhook retry resolving the queue from the container at handle-time', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob(SealedWebhookPayload::seal($payload, new FakeEncryptor()), attemptNumber: 1);
        $job->setId('webhook-retry-job-1');

        $dispatcher = new class () implements WebhookDispatcherInterface
        {
            public function dispatch(
                WebhookPayload $payload,
            ): WebhookResponse {
                throw new RuntimeException('Simulated failure');
            }
        };

        $retryQueue = new FakeQueue();

        $workerQueue = new class ($job) implements QueueInterface
        {
            private bool $popped = false;

            public function __construct(
                private readonly JobInterface $job,
            ) {}

            public function push(
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('webhook-retry-job-1');
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): PushResult {
                return PushResult::pushed('webhook-retry-job-1');
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
                return true;
            }

            public function release(
                JobInterface $job,
                int $delay = 0,
            ): bool {
                return true;
            }
        };

        $container = SerializableWebhookJobTestHelpers::container(
            $dispatcher,
            SerializableWebhookJobTestHelpers::deliveryService(),
            new FakeConfigRepository(['webhook.max_retries' => 3, 'webhook.retry_delay' => 60]),
            $retryQueue,
        );

        $envelope = SerializableWebhookJobTestHelpers::envelope();
        $failedRepository = SerializableWebhookJobTestHelpers::failedJobRepository();
        $queueConfig = SerializableWebhookJobTestHelpers::queueConfig();

        // Drive through Worker::work() so the container is injected via ContainerAwareJobInterface gate
        $worker = new Worker(
            $workerQueue,
            new FailedJobHandler(
                $failedRepository,
                $envelope,
                new JobRunner($container, $envelope, new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $queueConfig,
            new JobRunner($container, $envelope, new FakeEventDispatcher()),
            new RetryPolicy($queueConfig, new FakeClock()),
            clock: new FakeClock(),
        );
        $worker->work(once: true);

        // The retry job must have been pushed to the retryQueue (resolved from container at handle-time)
        expect($retryQueue->pushed)->toHaveCount(1)
            ->and($retryQueue->pushed[0]['job'])->toBeInstanceOf(DispatchWebhookJob::class);
    });
});
