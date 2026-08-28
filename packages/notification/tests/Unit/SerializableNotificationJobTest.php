<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Mail\Message;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Job\SendNotificationJob;
use Marko\Notification\NotificationManager;
use Marko\Notification\NotificationSender;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeConfigRepository;

class TestNotifiable implements NotifiableInterface
{
    public function routeNotificationFor(string $channel): mixed
    {
        return 'test@example.com';
    }

    public function getNotifiableId(): string|int
    {
        return 1;
    }

    public function getNotifiableType(): string
    {
        return self::class;
    }
}

class TestNotification implements NotificationInterface
{
    public array $sentTo = [];

    /** @return array<string> */
    public function channels(NotifiableInterface $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(NotifiableInterface $notifiable): Message
    {
        return new Message();
    }

    /** @return array<string, mixed> */
    public function toDatabase(NotifiableInterface $notifiable): array
    {
        return [];
    }
}

function createNotificationTestEnvelope(): JobEnvelope
{
    return new JobEnvelope(
        new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'notification-test-key'])),
    );
}

function createNotificationTestQueueConfig(): QueueConfig
{
    return new QueueConfig(new FakeConfigRepository([
        'queue.driver' => 'sync',
        'queue.connection' => 'default',
        'queue.queue' => 'default',
        'queue.retry_after' => 90,
        'queue.max_attempts' => 3,
    ]));
}

function createNotificationTestFailedJobRepository(): FailedJobRepositoryInterface
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

describe('SendNotificationJob serialization', function (): void {
    it('serializes and unserializes a SendNotificationJob without error', function (): void {
        $notifiable = new TestNotifiable();
        $notification = new TestNotification();

        $job = new SendNotificationJob($notifiable, $notification);

        $serialized = $job->serialize();
        $unserialized = Job::unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(SendNotificationJob::class);
    });

    it(
        'sends the notification to the notifiables when an unserialized SendNotificationJob is handled',
        function (): void {
            $notifiable = new TestNotifiable();
            $notification = new TestNotification();

            $job = new SendNotificationJob($notifiable, $notification);

            $serialized = $job->serialize();
            /** @var SendNotificationJob $unserialized */
            $unserialized = Job::unserialize($serialized);

            $sentNotifications = [];
            $channel = new class ($sentNotifications) implements ChannelInterface
            {
                /** @param array<array{notifiable: NotifiableInterface, notification: NotificationInterface}> $sentNotifications */
                public function __construct(
                    /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property used to track sent */
                    private array &$sentNotifications,
                ) {}

                public function send(
                    NotifiableInterface $notifiable,
                    NotificationInterface $notification,
                ): void {
                    $this->sentNotifications[] = compact('notifiable', 'notification');
                }
            };

            $manager = new NotificationManager();
            $manager->register('mail', $channel);
            $sender = new NotificationSender($manager);

            $container = new readonly class ($sender) implements ContainerInterface
            {
                public function __construct(
                    private NotificationSender $sender,
                ) {}

                public function get(string $id): object
                {
                    return match ($id) {
                        NotificationSender::class => $this->sender,
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

            $unserialized->setContainer($container);
            $unserialized->handle();

            expect($sentNotifications)->toHaveCount(1)
                ->and($sentNotifications[0]['notifiable'])->toBeInstanceOf(TestNotifiable::class);
        },
    );

    it('holds only serializable data and no live service instances on either job', function (): void {
        $notifiable = new TestNotifiable();
        $notification = new TestNotification();

        $job = new SendNotificationJob($notifiable, $notification);

        // Serialization must succeed (no closures, PDO, or live service objects)
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(SendNotificationJob::class);
    });

    it(
        'receives the container from the Worker so a webhook or notification job resolves its services in the real Worker path',
        function (): void {
            $notifiable = new TestNotifiable();
            $notification = new TestNotification();

            $job = new SendNotificationJob($notifiable, $notification);
            $job->setId('notification-job-1');

            $sentNotifications = [];
            $channel = new class ($sentNotifications) implements ChannelInterface
            {
                /** @param array<array{notifiable: NotifiableInterface, notification: NotificationInterface}> $sentNotifications */
                public function __construct(
                    /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property used to track sent */
                    private array &$sentNotifications,
                ) {}

                public function send(
                    NotifiableInterface $notifiable,
                    NotificationInterface $notification,
                ): void {
                    $this->sentNotifications[] = compact('notifiable', 'notification');
                }
            };

            $manager = new NotificationManager();
            $manager->register('mail', $channel);
            $sender = new NotificationSender($manager);

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
                    return 'notification-job-1';
                }

                public function later(
                    int $delay,
                    JobInterface $job,
                    ?string $queue = null,
                ): string {
                    return 'notification-job-1';
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

            $container = new readonly class ($sender) implements ContainerInterface
            {
                public function __construct(
                    private NotificationSender $sender,
                ) {}

                public function get(string $id): object
                {
                    return match ($id) {
                        NotificationSender::class => $this->sender,
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

            $envelope = createNotificationTestEnvelope();
            $failedRepository = createNotificationTestFailedJobRepository();
            $queueConfig = createNotificationTestQueueConfig();

            // Drive through Worker::work() — NOT a manual setContainer() call
            $worker = new Worker($workerQueue, $failedRepository, $queueConfig, $envelope, $container);
            $worker->work(once: true);

            // The notification was sent, proving Worker injected the container via ContainerAwareJobInterface gate
            expect($sentNotifications)->toHaveCount(1)
                    ->and($sentNotifications[0]['notifiable'])->toBeInstanceOf(TestNotifiable::class);
        },
    );
});
