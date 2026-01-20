<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverRegistry;

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
