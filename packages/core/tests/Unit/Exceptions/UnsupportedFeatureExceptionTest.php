<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Core\Exceptions\UnsupportedFeature;
use Marko\Core\Exceptions\UnsupportedFeatureException;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;

class PlainUnsupportedFeatureException extends Exception implements UnsupportedFeatureExceptionInterface
{
    public function __construct(
        private readonly UnsupportedFeature $unsupportedFeature,
    ) {
        parent::__construct($unsupportedFeature->message());
    }

    public function getFeature(): string
    {
        return $this->unsupportedFeature->feature;
    }

    public function getDriver(): string
    {
        return $this->unsupportedFeature->driver;
    }

    public function getContext(): string
    {
        return $this->unsupportedFeature->context();
    }

    public function getSuggestion(): string
    {
        return $this->unsupportedFeature->suggestion;
    }
}

it('builds the standard message naming the feature and the driver', function (): void {
    $unsupported = new UnsupportedFeature(
        feature: 'Pattern subscriptions',
        driver: 'pgsql',
        suggestion: 'Use the redis driver',
    );

    expect($unsupported->message())->toBe('Pattern subscriptions is not supported by the pgsql driver');
});

it('uses a caller-supplied message and context when given', function (): void {
    $unsupported = new UnsupportedFeature(
        feature: 'Pattern subscriptions',
        driver: 'pgsql',
        suggestion: 'Use the redis driver',
        context: 'While subscribing',
        message: 'Custom message',
    );

    expect($unsupported->message())->toBe('Custom message')
        ->and($unsupported->context())->toBe('While subscribing');
});

it('builds the default context naming the feature and the driver when no context is given', function (): void {
    $unsupported = new UnsupportedFeature(
        feature: 'Pattern subscriptions',
        driver: 'pgsql',
        suggestion: 'Use the redis driver',
    );

    expect($unsupported->context())->toBe('While using Pattern subscriptions on the pgsql driver');
});

it('creates an UnsupportedFeatureException for a driver with a suggestion', function (): void {
    $exception = UnsupportedFeatureException::forDriver(
        feature: 'Row locks',
        driver: 'SQLite',
        suggestion: 'Use the pgsql driver',
    );
    $custom = UnsupportedFeatureException::forDriver('Row locks', 'SQLite', 'Use pgsql', 'While locking');

    expect($exception->getMessage())->toBe('Row locks is not supported by the SQLite driver')
        ->and($exception->getContext())->toBe('While using Row locks on the SQLite driver')
        ->and($exception->getSuggestion())->toBe('Use the pgsql driver')
        ->and($custom->getContext())->toBe('While locking');
});

it('exposes the feature and driver through the interface', function (): void {
    $exception = UnsupportedFeatureException::forDriver('Row locks', 'SQLite', 'Use pgsql');

    expect($exception)->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
        ->and($exception->getFeature())->toBe('Row locks')
        ->and($exception->getDriver())->toBe('SQLite');
});

it('is a MarkoException and keeps the previous throwable', function (): void {
    $previous = new RuntimeException('root cause');
    $exception = UnsupportedFeatureException::forDriver('Row locks', 'SQLite', 'Use pgsql', previous: $previous);

    expect($exception)->toBeInstanceOf(MarkoException::class)
        ->and($exception->getPrevious())->toBe($previous);
});

it(
    'creates an UnsupportedFeatureException from an UnsupportedFeature value with its message, context and suggestion',
    function (): void {
        $exception = UnsupportedFeatureException::fromUnsupportedFeature(new UnsupportedFeature(
            feature: 'Row locks',
            driver: 'SQLite',
            suggestion: 'Use pgsql',
            context: 'While locking',
            message: 'Locking is unavailable',
        ));

        expect($exception->getMessage())->toBe('Locking is unavailable')
            ->and($exception->getContext())->toBe('While locking')
            ->and($exception->getSuggestion())->toBe('Use pgsql')
            ->and($exception->getFeature())->toBe('Row locks')
            ->and($exception->getDriver())->toBe('SQLite');
    },
);

it('lets a subclass of a non-Marko exception implement the interface through the helper', function (): void {
    $exception = new PlainUnsupportedFeatureException(new UnsupportedFeature(
        feature: 'Challenges',
        driver: 'token',
        suggestion: 'Use the session guard',
    ));

    expect($exception)->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
        ->and($exception)->not->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())->toBe('Challenges is not supported by the token driver')
        ->and($exception->getContext())->toBe('While using Challenges on the token driver')
        ->and($exception->getSuggestion())->toBe('Use the session guard')
        ->and($exception->getFeature())->toBe('Challenges')
        ->and($exception->getDriver())->toBe('token');
});
