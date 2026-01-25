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

        expect($job->observerClass)->toBe('App\Observers\EmailNotificationObserver');
    });

    it('stores serialized event', function (): void {
        $eventData = serialize(['user_id' => 123, 'action' => 'created']);

        $job = new AsyncObserverJob(
            observerClass: 'App\Observers\AuditObserver',
            eventData: $eventData,
        );

        expect($job->eventData)->toBe($eventData);
    });

    it('handle executes observer', function (): void {
        // Create a mock observer that tracks if it was called
        $capture = (object) ['called' => false, 'event' => null];

        $observer = new readonly class ($capture)
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

        expect($capture->called)->toBeTrue()
            ->and($capture->event)->toBeInstanceOf(stdClass::class)
            ->and($capture->event->type)->toBe('user.created')
            ->and($capture->event->userId)->toBe(42);
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
            ->and($unserialized->observerClass)->toBe('App\Observers\TestObserver')
            ->and($unserialized->eventData)->toBe(serialize(['test' => 'value']))
            ->and($unserialized->id)->toBe('async-123')
            ->and($unserialized->attempts)->toBe(1);
    });
});
