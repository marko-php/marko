<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\PubSub\Exceptions\PubSubException;

describe('PubSubException', function (): void {
    it('creates PubSubException extending MarkoException with static factory methods', function (): void {
        $exception = PubSubException::connectionFailed('redis', 'connection refused');

        expect($exception)->toBeInstanceOf(PubSubException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class);
    });

    it('creates connectionFailed exception with message, context, and suggestion', function (): void {
        $exception = PubSubException::connectionFailed('redis', 'connection refused');

        expect($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getMessage())->toContain('redis')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getContext())->toContain('redis')
            ->and($exception->getContext())->toContain('connection refused')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it('creates subscriptionFailed exception with message, context, and suggestion', function (): void {
        $exception = PubSubException::subscriptionFailed('orders.created', 'channel not found');

        expect($exception)->toBeInstanceOf(PubSubException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getMessage())->toContain('orders.created')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getContext())->toContain('orders.created')
            ->and($exception->getContext())->toContain('channel not found')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it('creates publishFailed exception with message, context, and suggestion', function (): void {
        $exception = PubSubException::publishFailed('orders.created', 'payload too large');

        expect($exception)->toBeInstanceOf(PubSubException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getMessage())->toContain('orders.created')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getContext())->toContain('orders.created')
            ->and($exception->getContext())->toContain('payload too large')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });
});
