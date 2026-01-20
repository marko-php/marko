<?php

declare(strict_types=1);

use Marko\Core\Event\Event;

it('creates Event base class for type-safe events', function (): void {
    $event = new class () extends Event {};

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->isPropagationStopped())->toBeFalse();
});

it('allows event to carry data accessible by observers', function (): void {
    // Event can carry any data through properties
    $event = new class ('John Doe', 'john@example.com') extends Event {
        public function __construct(
            public readonly string $name,
            public readonly string $email,
        ) {}
    };

    expect($event->name)->toBe('John Doe')
        ->and($event->email)->toBe('john@example.com');
});
