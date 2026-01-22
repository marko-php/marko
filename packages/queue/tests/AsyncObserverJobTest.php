<?php

declare(strict_types=1);

use Marko\Queue\AsyncObserverJob;
use Marko\Queue\Job;

describe('AsyncObserverJob', function (): void {
    it('extends Job', function (): void {
        $reflection = new ReflectionClass(AsyncObserverJob::class);

        expect($reflection->isSubclassOf(Job::class))->toBeTrue();
    });

    it('stores observer class', function (): void {
        $job = new AsyncObserverJob(
            observerClass: 'App\Observers\EmailNotificationObserver',
            eventData: serialize(['test' => 'data']),
        );

        expect($job->getObserverClass())->toBe('App\Observers\EmailNotificationObserver');
    });

    it('stores serialized event', function (): void {
        $eventData = serialize(['user_id' => 123, 'action' => 'created']);

        $job = new AsyncObserverJob(
            observerClass: 'App\Observers\AuditObserver',
            eventData: $eventData,
        );

        expect($job->getEventData())->toBe($eventData);
    });

    it('handle executes observer', function (): void {
        // Create a mock observer that tracks if it was called
        $observerCalled = false;
        $receivedEvent = null;

        $observer = new class ($observerCalled, $receivedEvent)
        {
            public function __construct(
                private bool &$called,
                private mixed &$event,
            ) {}

            public function handle(
                object $event,
            ): void {
                $this->called = true;
                $this->event = $event;
            }
        };

        // Create event to serialize
        $event = new stdClass();
        $event->type = 'user.created';
        $event->userId = 42;

        // Create job with observer class and serialized event
        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: serialize($event),
        );

        // Create a resolver callback to simulate container resolution
        $job->handle(fn (string $class): object => $observer);

        expect($observerCalled)->toBeTrue()
            ->and($receivedEvent)->toBeInstanceOf(stdClass::class)
            ->and($receivedEvent->type)->toBe('user.created')
            ->and($receivedEvent->userId)->toBe(42);
    });

    it('serializes and unserializes correctly', function (): void {
        $job = new AsyncObserverJob(
            observerClass: 'App\Observers\TestObserver',
            eventData: serialize(['test' => 'value']),
        );
        $job->setId('async-123');
        $job->incrementAttempts();

        $serialized = $job->serialize();
        $unserialized = AsyncObserverJob::unserialize($serialized);

        expect($unserialized)->toBeInstanceOf(AsyncObserverJob::class)
            ->and($unserialized->getObserverClass())->toBe('App\Observers\TestObserver')
            ->and($unserialized->getEventData())->toBe(serialize(['test' => 'value']))
            ->and($unserialized->getId())->toBe('async-123')
            ->and($unserialized->getAttempts())->toBe(1);
    });
});
