<?php

declare(strict_types=1);

use Marko\PubSub\Exceptions\NoDriverException;
use Marko\PubSub\Exceptions\PubSubException;

describe('NoDriverException', function (): void {
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

    it('pubsub NoDriverException reads from known-drivers.php and includes docs URLs', function (): void {
        $exception = NoDriverException::noDriverInstalled();

        expect($exception->getSuggestion())->toContain('https://marko.build/docs/packages/pubsub-redis/')
            ->and($exception->getSuggestion())->toContain('https://marko.build/docs/packages/pubsub-pgsql/');
    });
});
