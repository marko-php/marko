<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\QueueAsyncObserverDispatcher;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Marko\Queue\Worker;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeQueue;

class OrderShipped extends Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $carrier,
    ) {}
}

class SendShippingEmail
{
    /** @var list<OrderShipped> */
    public static array $handled = [];

    /** @noinspection PhpUnused - Invoked via reflection */
    public function handle(
        OrderShipped $event,
    ): void {
        self::$handled[] = $event;
    }
}

function createAsyncDispatchEnvelope(): JobEnvelope
{
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => 'async-dispatch-key'])));
}

function createAsyncDispatchContainer(
    QueueInterface $queue,
    JobEnvelope $jobEnvelope,
): Container {
    $container = new Container();
    $container->instance(QueueInterface::class, $queue);
    $container->instance(JobEnvelope::class, $jobEnvelope);
    $container->bind(AsyncObserverDispatcherInterface::class, QueueAsyncObserverDispatcher::class);

    return $container;
}

function createAsyncDispatchRegistry(): ObserverRegistry
{
    $registry = new ObserverRegistry();
    $registry->register(new ObserverDefinition(
        observerClass: SendShippingEmail::class,
        eventClass: OrderShipped::class,
        async: true,
    ));

    return $registry;
}

beforeEach(function (): void {
    SendShippingEmail::$handled = [];
});

describe('async observer dispatch through marko/queue', function (): void {
    it('pushes exactly one AsyncObserverJob and does not run the observer inline', function (): void {
        $queue = new FakeQueue();
        $container = createAsyncDispatchContainer($queue, createAsyncDispatchEnvelope());

        new EventDispatcher($container, createAsyncDispatchRegistry())->dispatch(new OrderShipped(42, 'UPS'));

        expect($queue->pushed)->toHaveCount(1)
            ->and($queue->pushed[0]['job'])->toBeInstanceOf(AsyncObserverJob::class)
            ->and($queue->pushed[0]['job']->observerClass)->toBe(SendShippingEmail::class)
            ->and(SendShippingEmail::$handled)->toBeEmpty();
    });

    it('runs the pushed job through the real Worker and invokes the observer with an equal event', function (): void {
        $jobEnvelope = createAsyncDispatchEnvelope();
        $outbox = new FakeQueue();
        $container = createAsyncDispatchContainer($outbox, $jobEnvelope);
        $event = new OrderShipped(42, 'UPS');

        new EventDispatcher($container, createAsyncDispatchRegistry())->dispatch($event);

        // A real driver stores the job serialized; FakeQueue hands back the same object, so round-trip it here.
        $storedJob = AsyncObserverJob::unserialize($outbox->pushed[0]['job']->serialize());
        $inbox = new FakeQueue();
        $inbox->push($storedJob);
        $failedJobRepository = new class () implements FailedJobRepositoryInterface
        {
            /** @var list<FailedJob> */
            public array $stored = [];

            public function store(FailedJob $failedJob): void
            {
                $this->stored[] = $failedJob;
            }

            public function all(): array
            {
                return $this->stored;
            }

            public function find(string $id): ?FailedJob
            {
                return null;
            }

            public function delete(string $id): bool
            {
                return false;
            }

            public function clear(): int
            {
                return 0;
            }

            public function count(): int
            {
                return count($this->stored);
            }
        };
        $config = new QueueConfig(new FakeConfigRepository([
            'queue.queue' => 'default',
            'queue.max_attempts' => 3,
        ]));

        new Worker(
            $inbox,
            new FailedJobHandler(
                $failedJobRepository,
                $jobEnvelope,
                new JobRunner($container, $jobEnvelope, new FakeEventDispatcher()),
                new FakeEventDispatcher(),
                new FakeClock(),
            ),
            $config,
            new JobRunner($container, $jobEnvelope, new FakeEventDispatcher()),
            new RetryPolicy($config, new FakeClock()),
        )->work(
            once: true,
        );

        expect(SendShippingEmail::$handled)->toHaveCount(1)
            ->and(SendShippingEmail::$handled[0])->toEqual($event)
            ->and(SendShippingEmail::$handled[0])->not->toBe($event)
            ->and($failedJobRepository->stored)->toBeEmpty()
            ->and($inbox->size())->toBe(0);
    });
});
