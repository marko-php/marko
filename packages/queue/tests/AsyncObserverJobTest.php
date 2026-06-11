<?php

declare(strict_types=1);

use Marko\Encryption\Config\EncryptionConfig;
use Marko\Queue\AsyncObserverJob;
use Marko\Queue\Exceptions\SerializationException;
use Marko\Queue\Job;
use Marko\Queue\JobEnvelope;
use Marko\Testing\Fake\FakeConfigRepository;

function createAsyncObserverJobEnvelope(
    string $key = 'test-key-for-async-observer',
): JobEnvelope {
    return new JobEnvelope(new EncryptionConfig(new FakeConfigRepository(['encryption.key' => $key])));
}

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

    it('verifies the envelope before unserializing AsyncObserverJob event data', function (): void {
        $envelope = createAsyncObserverJobEnvelope();
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

        $event = new stdClass();
        $event->type = 'user.signed_up';
        $event->userId = 99;

        // Wrap the event data with the envelope at construction time
        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: $envelope->wrap(serialize($event)),
        );

        $job->handle(fn (string $class): object => $observer, $envelope);

        expect($capture->called)->toBeTrue()
            ->and($capture->event->type)->toBe('user.signed_up')
            ->and($capture->event->userId)->toBe(99);
    });

    it('throws SerializationException when AsyncObserverJob event data is tampered', function (): void {
        $envelope = createAsyncObserverJobEnvelope();

        $fakeHmac = str_repeat('c', 64);
        $tamperedEventData = $fakeHmac . '.O:8:"EvilObj":0:{}';

        $job = new AsyncObserverJob(
            observerClass: 'SomeObserver',
            eventData: $tamperedEventData,
        );

        expect(fn () => $job->handle(fn (string $c): object => new stdClass(), $envelope))
            ->toThrow(SerializationException::class);
    });
});
