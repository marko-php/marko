<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\Testing\Exceptions\DatabaseTestException;
use Marko\Testing\Exceptions\UnsupportedDatabaseTestFeatureException;

describe('DatabaseTestException', function (): void {
    it('describes the testing-only allowlist in the destructive environment exception', function (): void {
        $exception = DatabaseTestException::destructiveInEnvironment('truncate the entity tables', 'staging');

        expect($exception->getMessage())
            ->toBe("Refusing to truncate the entity tables in the 'staging' environment.")
            ->and($exception->getContext())
            ->toBe('This operation deletes data. It runs only when APP_ENV is a testing environment (testing, test).')
            ->and($exception->getSuggestion())
            ->toContain('APP_ENV=testing')
            ->toContain('MARKO_ENV')
            ->toContain('dedicated test database');
    });

    it(
        'makes TruncateDatabase\'s unsupported driver exception a DatabaseTestException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = DatabaseTestException::unsupportedDriver('oracle');

            expect($exception)
                ->toBeInstanceOf(UnsupportedDatabaseTestFeatureException::class)
                ->toBeInstanceOf(DatabaseTestException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
                ->and($exception->getFeature())->toBe('TruncateDatabase')
                ->and($exception->getDriver())->toBe('oracle');
        },
    );

    it(
        'makes RefreshDatabase\'s after-commit callbacks exception a DatabaseTestException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = DatabaseTestException::cannotRunPendingAfterCommitCallbacks('App\\Connection');

            expect($exception)
                ->toBeInstanceOf(UnsupportedDatabaseTestFeatureException::class)
                ->toBeInstanceOf(DatabaseTestException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
                ->and($exception->getFeature())->toBe('pending after-commit callbacks')
                ->and($exception->getDriver())->toBe('App\\Connection');
        },
    );

    it('keeps the existing database test exception messages, contexts and suggestions', function (): void {
        $truncate = DatabaseTestException::unsupportedDriver('oracle');
        $callbacks = DatabaseTestException::cannotRunPendingAfterCommitCallbacks('App\\Connection');

        expect($truncate->getMessage())->toBe("TruncateDatabase does not support the 'oracle' driver.")
            ->and($truncate->getContext())->toBe('Building the TRUNCATE statements for the entity tables')
            ->and($truncate->getSuggestion())->toBe(
                'Use the pgsql, mysql or sqlite driver, or use RefreshDatabase, which works with any transactional driver.',
            )
            ->and($callbacks->getMessage())->toBe(
                "Connection 'App\\Connection' cannot run pending after-commit callbacks.",
            )
            ->and($callbacks->getContext())->toBe(
                'RefreshDatabase::runAfterCommitCallbacks() needs a connection implementing Marko\\Database\\Connection\\PendingAfterCommitInterface.',
            )
            ->and($callbacks->getSuggestion())->toBe(
                'Use the marko/database-pgsql, marko/database-mysql or marko/database-sqlite driver, or assert on after-commit work with TruncateDatabase and a real commit.',
            );
    });
});
