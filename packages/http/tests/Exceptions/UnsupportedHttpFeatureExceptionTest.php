<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\Http\Exceptions\InvalidRequestOptionException;
use Marko\Http\Exceptions\UnsupportedHttpFeatureException;

describe('unsupportedByDriver', function (): void {
    it(
        'makes the http driver option exception an InvalidRequestOptionException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = InvalidRequestOptionException::unsupportedByDriver('resolve_to', 'Guzzle', 'no support');

            expect($exception)
                ->toBeInstanceOf(UnsupportedHttpFeatureException::class)
                ->toBeInstanceOf(InvalidRequestOptionException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('reports the feature and driver of the driver option exception', function (): void {
        $exception = InvalidRequestOptionException::unsupportedByDriver('resolve_to', 'Guzzle', 'no support');

        expect($exception->getFeature())->toBe('resolve_to')
            ->and($exception->getDriver())->toBe('Guzzle');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $exception = InvalidRequestOptionException::unsupportedByDriver('resolve_to', 'Guzzle', 'no support');

        expect($exception->getMessage())->toBe("HTTP request option 'resolve_to' is not supported by Guzzle.")
            ->and($exception->getContext())->toBe('no support')
            ->and($exception->getSuggestion())->toBe(
                "Use an HTTP client driver that supports 'resolve_to', or remove the option. "
                . 'The option is never silently ignored, because callers rely on it for security.',
            );
    });
});
