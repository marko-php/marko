<?php

declare(strict_types=1);

use Marko\Broadcasting\Exceptions\BroadcastException;
use Marko\Broadcasting\Exceptions\UnsupportedBroadcastFeatureException;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;

describe('UnsupportedBroadcastFeatureException', function (): void {
    it(
        'makes the presence channels exception a BroadcastException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = BroadcastException::presenceChannelsUnsupported('Amphp', 'room.1');

            expect($exception)
                ->toBeInstanceOf(UnsupportedBroadcastFeatureException::class)
                ->toBeInstanceOf(BroadcastException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('reports the feature and driver of each capability exception', function (): void {
        $exception = BroadcastException::presenceChannelsUnsupported('Mercure', 'room.1');

        expect($exception->getFeature())->toBe('presence channels')
            ->and($exception->getDriver())->toBe('Mercure');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $exception = BroadcastException::presenceChannelsUnsupported('Mercure', 'room.1');

        expect($exception->getMessage())->toBe("Presence channel 'room.1' is not supported by Mercure.")
            ->and($exception->getContext())->toBe('The Mercure driver cannot track presence channel members')
            ->and($exception->getSuggestion())->toBe(
                'Use a PrivateChannel instead, or switch to the Pusher driver (marko/broadcasting-pusher) for presence channels.',
            );
    });
});
