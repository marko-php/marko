<?php

declare(strict_types=1);

use Marko\DevServer\Detection\PubSubDetector;

it('creates PubSubDetector that checks if marko/pubsub is installed', function (): void {
    $detector = new PubSubDetector();

    expect($detector)->toBeInstanceOf(PubSubDetector::class);
});

it('returns pubsub:listen command string when package is detected', function (): void {
    $detector = new class () extends PubSubDetector
    {
        protected function isPubSubInstalled(): bool
        {
            return true;
        }
    };

    expect($detector->detect())->toBe('marko pubsub:listen');
});

it('returns null when marko/pubsub is not installed', function (): void {
    $detector = new class () extends PubSubDetector
    {
        protected function isPubSubInstalled(): bool
        {
            return false;
        }
    };

    expect($detector->detect())->toBeNull();
});
