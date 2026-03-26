<?php

declare(strict_types=1);

use Marko\PubSub\Exceptions\NoDriverException;
use Marko\PubSub\Exceptions\PubSubException;

describe('NoDriverException', function (): void {
    it('has DRIVER_PACKAGES constant listing marko/pubsub-pgsql and marko/pubsub-redis', function (): void {
        $reflection = new ReflectionClass(NoDriverException::class);
        $constant = $reflection->getReflectionConstant('DRIVER_PACKAGES');

        expect($constant)->not->toBeFalse()
            ->and($constant->getValue())->toContain('marko/pubsub-pgsql')
            ->and($constant->getValue())->toContain('marko/pubsub-redis');
    });

    it('provides suggestion with composer require commands for all driver packages', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('composer require marko/pubsub-pgsql')
            ->and($exception->getSuggestion())->toContain('composer require marko/pubsub-redis');
    });

    it('includes context about resolving pub/sub interfaces', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getContext())->toContain('pub/sub');
    });

    it('extends PubSubException', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(PubSubException::class);
    });
});
