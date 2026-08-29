<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Jobs;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Contracts\WebhookAttemptRepositoryInterface;
use Marko\Webhook\Contracts\WebhookDispatcherInterface;
use Marko\Webhook\Entity\WebhookAttempt;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Sending\WebhookDeliveryService;
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
            secret: 'test-secret',
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

            public function store(FailedJob $failedJob): void
            {
                $this->storedJobs[$failedJob->id] = $failedJob;
            }

            /** @return array<FailedJob> */
            public function all(): array
            {
                return array_values($this->storedJobs);
            }

            public function find(string $id): ?FailedJob
            {
                return $this->storedJobs[$id] ?? null;
            }

            public function delete(string $id): bool
            {
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
            public function save(WebhookAttempt $attempt): WebhookAttempt
            {
                return $attempt;
            }
        };

        return new WebhookDeliveryService($stubRepo);
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

            public function get(string $id): object
            {
                return match ($id) {
                    WebhookDispatcherInterface::class => $this->dispatcher,
                    WebhookDeliveryService::class => $this->deliveryService,
                    ConfigRepositoryInterface::class => $this->config,
                    QueueInterface::class => $this->queue,
                    default => throw new RuntimeException("No binding for: $id"),
                };
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
}

describe('DispatchWebhookJob serialization', function (): void {
    it('serializes and unserializes a DispatchWebhookJob without error', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob($payload);

        $serialized = $job->serialize();
        $unserialized = Job::unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(DispatchWebhookJob::class);
    });

    it('dispatches the webhook payload when a unserialized DispatchWebhookJob is handled', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob($payload);

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

            public function dispatch(WebhookPayload $payload): WebhookResponse
            {
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

    it('re-enqueues a retry job that is itself serializable after a webhook failure', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob($payload, attemptNumber: 1);

        $serialized = $job->serialize();
        /** @var DispatchWebhookJob $unserialized */
        $unserialized = Job::unserialize($serialized);

        $retryQueue = new FakeQueue();

        $dispatcher = new class () implements WebhookDispatcherInterface
        {
            public function dispatch(WebhookPayload $payload): WebhookResponse
            {
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
        $job = new DispatchWebhookJob($payload, attemptNumber: 2);

        // Serialization must succeed (no closures, PDO, or live service objects)
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(DispatchWebhookJob::class);
    });

    it(
        'receives the container from the Worker so a webhook or notification job resolves its services in the real Worker path',
        function (): void {
            $payload = SerializableWebhookJobTestHelpers::payload();
            $job = new DispatchWebhookJob($payload);
            $job->setId('webhook-job-1');

            $dispatched = [];
            $dispatcher = new class ($dispatched) implements WebhookDispatcherInterface
            {
                /** @param array<WebhookPayload> $dispatched */
                public function __construct(
                    /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property used to track dispatch */
                    private array &$dispatched,
                ) {}

                public function dispatch(WebhookPayload $payload): WebhookResponse
                {
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
                ): string {
                    return 'webhook-job-1';
                }

                public function later(
                    int $delay,
                    JobInterface $job,
                    ?string $queue = null,
                ): string {
                    return 'webhook-job-1';
                }

                public function pop(?string $queue = null): ?JobInterface
                {
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
                    string $jobId,
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
            $worker = new Worker($workerQueue, $failedRepository, $queueConfig, $envelope, $container);
            $worker->work(once: true);

            // The dispatcher was called, proving Worker injected the container via the ContainerAwareJobInterface gate
            expect($dispatched)->toHaveCount(1)
                    ->and($dispatched[0])->toBe($payload);
        },
    );

    it('re-enqueues a webhook retry resolving the queue from the container at handle-time', function (): void {
        $payload = SerializableWebhookJobTestHelpers::payload();
        $job = new DispatchWebhookJob($payload, attemptNumber: 1);
        $job->setId('webhook-retry-job-1');

        $dispatcher = new class () implements WebhookDispatcherInterface
        {
            public function dispatch(WebhookPayload $payload): WebhookResponse
            {
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
            ): string {
                return 'webhook-retry-job-1';
            }

            public function later(
                int $delay,
                JobInterface $job,
                ?string $queue = null,
            ): string {
                return 'webhook-retry-job-1';
            }

            public function pop(?string $queue = null): ?JobInterface
            {
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
                string $jobId,
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
        $worker = new Worker($workerQueue, $failedRepository, $queueConfig, $envelope, $container);
        $worker->work(once: true);

        // The retry job must have been pushed to the retryQueue (resolved from container at handle-time)
        expect($retryQueue->pushed)->toHaveCount(1)
            ->and($retryQueue->pushed[0]['job'])->toBeInstanceOf(DispatchWebhookJob::class);
    });
});
