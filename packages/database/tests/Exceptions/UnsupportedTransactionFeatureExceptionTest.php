<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\Database\Exceptions\TransactionException;

describe('UnsupportedTransactionFeatureException', function (): void {
    it(
        'makes both transaction capability factories return a TransactionException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exceptions = [
                TransactionException::connectionDoesNotSupportTransactions('App\\Conn'),
                TransactionException::cannotRunPendingAfterCommitCallbacks('App\\Conn'),
            ];

            expect($exceptions[0])
                ->toBeInstanceOf(TransactionException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
                ->and($exceptions[0]->getFeature())->toBe('transactions')
                ->and($exceptions[0]->getDriver())->toBe('App\\Conn')
                ->and($exceptions[1])
                ->toBeInstanceOf(TransactionException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class)
                ->and($exceptions[1]->getFeature())->toBe('pending after-commit callbacks')
                ->and($exceptions[1]->getDriver())->toBe('App\\Conn');
        },
    );

    it('keeps the existing transaction exception messages, contexts and suggestions', function (): void {
        $first = TransactionException::connectionDoesNotSupportTransactions('App\\Conn');
        $second = TransactionException::cannotRunPendingAfterCommitCallbacks('App\\Conn');

        expect($first->getMessage())->toBe("Connection 'App\\Conn' does not support transactions")
            ->and($first->getContext())->toBe(
                'Resolving TransactionInterface returns the shared ConnectionInterface instance, which must also implement TransactionInterface',
            )
            ->and($first->getSuggestion())->toBe(
                'Bind ConnectionInterface to a connection that implements TransactionInterface, or bind TransactionInterface explicitly',
            )
            ->and($second->getMessage())->toBe("Connection 'App\\Conn' cannot run pending after-commit callbacks")
            ->and($second->getContext())->toBe(
                'Running after-commit callbacks queued inside a transaction that is never committed (a RefreshDatabase test)',
            )
            ->and($second->getSuggestion())->toContain('Marko\Database\Connection\PendingAfterCommitInterface');
    });
});
