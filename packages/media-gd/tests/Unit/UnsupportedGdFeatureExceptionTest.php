<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\MediaGd\Exceptions\GdProcessingException;
use Marko\MediaGd\Exceptions\UnsupportedGdFeatureException;

describe('unsupportedFormat', function (): void {
    it(
        'makes the gd unsupported format exception a GdProcessingException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = GdProcessingException::unsupportedFormat('bmp');

            expect($exception)
                ->toBeInstanceOf(UnsupportedGdFeatureException::class)
                ->toBeInstanceOf(GdProcessingException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('reports the feature and driver of each capability exception', function (): void {
        $exception = GdProcessingException::unsupportedFormat('bmp');

        expect($exception->getFeature())->toBe("image format 'bmp'")
            ->and($exception->getDriver())->toBe('GD');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $exception = GdProcessingException::unsupportedFormat('bmp');

        expect($exception->getMessage())->toBe("Unsupported image format: 'bmp'")
            ->and($exception->getContext())->toBe('Converting image format')
            ->and($exception->getSuggestion())->toBe('Use one of the supported formats: jpeg, png, gif, webp');
    });
});
