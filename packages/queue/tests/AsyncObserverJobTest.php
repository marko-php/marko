<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
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

function createStubContainer(object $observer): ContainerInterface
{
    return new readonly class ($observer) implements ContainerInterface
    {
        public function __construct(
            private object $observer,
        ) {}

        public function get(string $id): object
        {
            return $this->observer;
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
        $event->type = 'user.created';
        $event->userId = 42;

        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: serialize($event),
        );

        $job->setContainer(createStubContainer($observer));
        $job->handle();

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

        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: $envelope->wrap(serialize($event)),
        );

        $job->setContainer(createStubContainer($observer));
        $job->setJobEnvelope($envelope);
        $job->handle();

        expect($capture->called)->toBeTrue()
            ->and($capture->event->type)->toBe('user.signed_up')
            ->and($capture->event->userId)->toBe(99);
    });

    it('throws SerializationException when AsyncObserverJob event data is tampered', function (): void {
        $envelope = createAsyncObserverJobEnvelope();

        $fakeHmac = str_repeat('c', 64);
        $tamperedEventData = $fakeHmac . '.O:8:"EvilObj":0:{}';

        $observer = new readonly class ()
        {
            /** @noinspection PhpUnused - Invoked via reflection */
            public function handle(object $event): void {}
        };

        $job = new AsyncObserverJob(
            observerClass: $observer::class,
            eventData: $tamperedEventData,
        );

        $job->setContainer(createStubContainer($observer));
        $job->setJobEnvelope($envelope);

        expect(fn () => $job->handle())
            ->toThrow(SerializationException::class);
    });

    it(
        'resolves the observer from the container and calls handle() with the deserialized event when the async observer job is processed',
        function (): void {
            $capture = (object) ['called' => false, 'event' => null];

            $observer = new readonly class ($capture)
            {
                public function __construct(
                    private object $capture,
                ) {}

                public function handle(object $event): void
                {
                    $this->capture->called = true;
                    $this->capture->event = $event;
                }
            };

            $event = new stdClass();
            $event->value = 'resolved-from-container';

            $job = new AsyncObserverJob(
                observerClass: $observer::class,
                eventData: serialize($event),
            );

            $job->setContainer(createStubContainer($observer));
            $job->handle();

            expect($capture->called)->toBeTrue()
                ->and($capture->event->value)->toBe('resolved-from-container');
        },
    );

    it(
        'no longer silently does nothing when handle() is called (it resolves and invokes the observer using the injected container)',
        function (): void {
            $capture = (object) ['called' => false];

            $observer = new readonly class ($capture)
            {
                public function __construct(
                    private object $capture,
                ) {}

                public function handle(object $event): void
                {
                    $this->capture->called = true;
                }
            };

            $event = new stdClass();

            $job = new AsyncObserverJob(
                observerClass: $observer::class,
                eventData: serialize($event),
            );

            $job->setContainer(createStubContainer($observer));
            $job->handle();

            expect($capture->called)->toBeTrue();
        },
    );

    it('surfaces a loud error when the observer class cannot be resolved (no silent swallow)', function (): void {
        $container = new class () implements ContainerInterface
        {
            public function get(string $id): never
            {
                throw new RuntimeException("Cannot resolve: $id");
            }

            public function has(string $id): bool
            {
                return false;
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

        $job = new AsyncObserverJob(
            observerClass: 'NonExistentObserver',
            eventData: serialize(new stdClass()),
        );

        $job->setContainer($container);

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class, 'Cannot resolve: NonExistentObserver');
    });

    it('throws a loud error when handle() runs before a container has been set (never a no-op)', function (): void {
        $job = new AsyncObserverJob(
            observerClass: 'SomeObserver',
            eventData: serialize(new stdClass()),
        );

        expect(fn () => $job->handle())
            ->toThrow(RuntimeException::class);
    });

    it(
        'serializes and unserializes without the container or envelope (both are null across the serialize round-trip; no magic methods)',
        function (): void {
            $observer = new readonly class ()
            {
                /** @noinspection PhpUnused - Invoked via reflection */
                public function handle(object $event): void {}
            };

            $job = new AsyncObserverJob(
                observerClass: $observer::class,
                eventData: serialize(new stdClass()),
            );

            $serialized = $job->serialize();
            /** @var AsyncObserverJob $unserialized */
            $unserialized = AsyncObserverJob::unserialize($serialized);

            expect($unserialized)->toBeInstanceOf(AsyncObserverJob::class)
                ->and($unserialized->observerClass)->toBe($observer::class);
        },
    );
});
