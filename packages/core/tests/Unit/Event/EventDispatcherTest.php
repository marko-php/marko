<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\JobInterface;
use Marko\Queue\QueueInterface;

// Test fixtures
class DispatcherTestEvent extends Event
{
    public array $handledBy = [];
}

class FirstObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'first';
    }
}

class SecondObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'second';
    }
}

class LowPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'low';
    }
}

class HighPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'high';
    }
}

class MediumPriorityObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'medium';
    }
}

// Dependency for testing DI
class LoggerDependency
{
    public function log(
        string $message,
    ): string {
        return "logged: $message";
    }
}

class ObserverWithDependency
{
    public function __construct(
        private readonly LoggerDependency $logger,
    ) {}

    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = $this->logger->log('handled');
    }
}

class StoppingObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'stopping';
        $event->stopPropagation();
    }
}

class AfterStopObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'after-stop';
    }
}

it('dispatches event to all registered observers', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    $registry->register(new ObserverDefinition(
        observerClass: FirstObserver::class,
        eventClass: DispatcherTestEvent::class,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: SecondObserver::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    expect($event->handledBy)->toContain('first')
        ->and($event->handledBy)->toContain('second')
        ->and($event->handledBy)->toHaveCount(2);
});

it('executes observers in priority order (higher first)', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // Register in non-priority order to verify sorting
    $registry->register(new ObserverDefinition(
        observerClass: LowPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 10,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: HighPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: MediumPriorityObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 50,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Higher priority should execute first
    expect($event->handledBy)->toBe(['high', 'medium', 'low']);
});

it('passes event object to observer handle method', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // Observer that captures the event
    $observerClass = new class ()
    {
        public static ?Event $capturedEvent = null;

        public function handle(
            DispatcherTestEvent $event,
        ): void {
            self::$capturedEvent = $event;
        }
    };

    $registry->register(new ObserverDefinition(
        observerClass: $observerClass::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    expect($observerClass::$capturedEvent)->toBe($event);
});

it('injects observer dependencies via container', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    $registry->register(new ObserverDefinition(
        observerClass: ObserverWithDependency::class,
        eventClass: DispatcherTestEvent::class,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // The dependency was injected and used
    expect($event->handledBy)->toBe(['logged: handled']);
});

it('supports stopping event propagation from observer', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // StoppingObserver has higher priority, runs first, stops propagation
    $registry->register(new ObserverDefinition(
        observerClass: StoppingObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 100,
    ));
    $registry->register(new ObserverDefinition(
        observerClass: AfterStopObserver::class,
        eventClass: DispatcherTestEvent::class,
        priority: 50,
    ));

    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Only the stopping observer ran, not the one after
    expect($event->handledBy)->toBe(['stopping'])
        ->and($event->propagationStopped)->toBeTrue();
});

it('accepts optional queue', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();
    $queue = new class () implements QueueInterface
    {
        public array $jobs = [];

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            $id = uniqid();
            $this->jobs[] = $job;

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
            return true;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            return true;
        }
    };

    // Create with queue
    $dispatcherWithQueue = new EventDispatcher($container, $registry, $queue);
    expect($dispatcherWithQueue)->toBeInstanceOf(EventDispatcher::class);

    // Create without queue (optional)
    $dispatcherWithoutQueue = new EventDispatcher($container, $registry);
    expect($dispatcherWithoutQueue)->toBeInstanceOf(EventDispatcher::class);

    // Verify the type hint is properly defined (this will fail if parameter doesn't exist)
    $reflection = new ReflectionClass(EventDispatcher::class);
    $constructor = $reflection->getConstructor();
    $params = $constructor->getParameters();

    expect($params)->toHaveCount(3);
    expect($params[2]->getName())->toBe('queue');
    expect($params[2]->isOptional())->toBeTrue();
    expect($params[2]->getType()?->getName())->toBe(QueueInterface::class);
});

class AsyncTestObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'async';
    }
}

it('queues async observers', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();
    $queue = new class () implements QueueInterface
    {
        public array $jobs = [];

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            $id = uniqid();
            $this->jobs[] = $job;

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
            return true;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            return true;
        }
    };

    // Register an async observer
    $registry->register(new ObserverDefinition(
        observerClass: AsyncTestObserver::class,
        eventClass: DispatcherTestEvent::class,
        async: true,
    ));

    $dispatcher = new EventDispatcher($container, $registry, $queue);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Event was NOT handled immediately (queued instead)
    expect($event->handledBy)->toBeEmpty();

    // Job was pushed to queue
    expect($queue->jobs)->toHaveCount(1);

    // Job is an AsyncObserverJob
    expect($queue->jobs[0])->toBeInstanceOf(AsyncObserverJob::class);
    expect($queue->jobs[0]->getObserverClass())->toBe(AsyncTestObserver::class);
});

class SyncTestObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'sync';
    }
}

it('executes sync observers immediately', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();
    $queue = new class () implements QueueInterface
    {
        public array $jobs = [];

        public function push(
            JobInterface $job,
            ?string $queue = null,
        ): string {
            $id = uniqid();
            $this->jobs[] = $job;

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
            return true;
        }

        public function release(
            string $jobId,
            int $delay = 0,
        ): bool {
            return true;
        }
    };

    // Register a sync observer (async=false, the default)
    $registry->register(new ObserverDefinition(
        observerClass: SyncTestObserver::class,
        eventClass: DispatcherTestEvent::class,
        async: false,
    ));

    $dispatcher = new EventDispatcher($container, $registry, $queue);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Event was handled immediately
    expect($event->handledBy)->toBe(['sync']);

    // No job was pushed to queue
    expect($queue->jobs)->toBeEmpty();
});

class AsyncFallbackObserver
{
    public function handle(
        DispatcherTestEvent $event,
    ): void {
        $event->handledBy[] = 'async-fallback';
    }
}

it('falls back when no queue', function (): void {
    $container = new Container();
    $registry = new ObserverRegistry();

    // Register an async observer
    $registry->register(new ObserverDefinition(
        observerClass: AsyncFallbackObserver::class,
        eventClass: DispatcherTestEvent::class,
        async: true,
    ));

    // Create dispatcher WITHOUT queue
    $dispatcher = new EventDispatcher($container, $registry);
    $event = new DispatcherTestEvent();

    $dispatcher->dispatch($event);

    // Event was handled immediately (graceful degradation)
    expect($event->handledBy)->toBe(['async-fallback']);
});
