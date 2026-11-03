<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\PubSub\Exceptions\PubSubException;
use Marko\PubSub\Exceptions\UnsupportedPubSubFeatureException;

describe('UnsupportedPubSubFeatureException', function (): void {
    it(
        'makes the pattern subscription exception a PubSubException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = PubSubException::patternSubscriptionNotSupported('pgsql');

            expect($exception)
                ->toBeInstanceOf(UnsupportedPubSubFeatureException::class)
                ->toBeInstanceOf(PubSubException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('reports the feature and driver of each capability exception', function (): void {
        $exception = PubSubException::patternSubscriptionNotSupported('pgsql');

        expect($exception->getFeature())->toBe('pattern subscriptions')
            ->and($exception->getDriver())->toBe('pgsql');
    });

    it('keeps the existing pubsub exception messages, contexts and suggestions', function (): void {
        $exception = PubSubException::patternSubscriptionNotSupported('pgsql');

        expect($exception->getMessage())->toBe("Pattern subscriptions are not supported by the 'pgsql' driver")
            ->and($exception->getContext())->toBe('While attempting to call psubscribe() on the pgsql pub/sub driver')
            ->and($exception->getSuggestion())->toBe('Use the Redis driver if you need pattern-based subscriptions');
    });
});
