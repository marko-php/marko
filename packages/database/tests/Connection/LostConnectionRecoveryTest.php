<?php

declare(strict_types=1);

use Marko\Database\Connection\LostConnectionRecovery;
use Marko\Database\Connection\TransactionState;
use Marko\Database\Exceptions\LostConnectionException;

function lostConnection(): LostConnectionException
{
    return LostConnectionException::fromDriverError(
        new PDOException('server closed the connection unexpectedly'),
        'SELECT 1',
        [],
    );
}

it('returns the result of the attempt when it succeeds without dropping the connection', function (): void {
    $drops = 0;

    $result = (new LostConnectionRecovery())->run(
        attempt: fn (): string => 'rows',
        dropConnection: function () use (&$drops): void {
            $drops++;
        },
        transactionState: new TransactionState(),
    );

    expect($result)->toBe('rows')
        ->and($drops)->toBe(0);
});

it(
    'drops the connection and runs the attempt once more when the connection is lost outside a transaction',
    function (): void {
        $attempts = 0;
        $drops = 0;

        $result = (new LostConnectionRecovery())->run(
            attempt: function () use (&$attempts): string {
                $attempts++;

                if ($attempts === 1) {
                    throw lostConnection();
                }

                return 'rows';
            },
            dropConnection: function () use (&$drops): void {
                $drops++;
            },
            transactionState: new TransactionState(),
        );

        expect($result)->toBe('rows')
            ->and($attempts)->toBe(2)
            ->and($drops)->toBe(1);
    },
);

it(
    'drops the connection and rethrows when the second attempt loses the connection too, without a third attempt',
    function (): void {
        $attempts = 0;
        $drops = 0;
        $second = lostConnection();

        $caught = null;

        try {
            (new LostConnectionRecovery())->run(
                attempt: function () use (&$attempts, $second): string {
                    $attempts++;

                    throw $attempts === 1 ? lostConnection() : $second;
                },
                dropConnection: function () use (&$drops): void {
                    $drops++;
                },
                transactionState: new TransactionState(),
            );
        } catch (LostConnectionException $exception) {
            $caught = $exception;
        }

        expect($caught)->toBe($second)
            ->and($attempts)->toBe(2)
            ->and($drops)->toBe(2);
    },
);

it(
    'drops the connection, marks the transaction lost and throws the transaction variant without retrying when the connection is lost inside a transaction',
    function (): void {
        $attempts = 0;
        $drops = 0;
        $transactionState = new TransactionState();
        $transactionState->begin();

        $caught = null;

        try {
            (new LostConnectionRecovery())->run(
                attempt: function () use (&$attempts): string {
                    $attempts++;

                    throw lostConnection();
                },
                dropConnection: function () use (&$drops): void {
                    $drops++;
                },
                transactionState: $transactionState,
            );
        } catch (LostConnectionException $exception) {
            $caught = $exception;
        }

        expect($caught)->toBeInstanceOf(LostConnectionException::class)
            ->and($caught->getMessage())->toContain('inside a transaction')
            ->and($attempts)->toBe(1)
            ->and($drops)->toBe(1)
            ->and($transactionState->isLost())->toBeTrue()
            ->and($transactionState->level())->toBe(1);
    },
);

it('keeps the sql and previous exception of the lost connection on the transaction variant', function (): void {
    $transactionState = new TransactionState();
    $transactionState->begin();
    $driverError = new PDOException('server closed the connection unexpectedly');

    $caught = null;

    try {
        (new LostConnectionRecovery())->run(
            attempt: function () use ($driverError): never {
                throw LostConnectionException::fromDriverError($driverError, 'UPDATE t SET a = ?', ['x']);
            },
            dropConnection: function (): void {},
            transactionState: $transactionState,
        );
    } catch (LostConnectionException $exception) {
        $caught = $exception;
    }

    expect($caught->sql())->toBe('UPDATE t SET a = ?')
        ->and($caught->getPrevious())->toBe($driverError);
});

it('refuses without running the attempt while the transaction is marked lost', function (): void {
    $attempts = 0;
    $transactionState = new TransactionState();
    $transactionState->begin();
    $transactionState->markLost();

    $caught = null;

    try {
        (new LostConnectionRecovery())->run(
            attempt: function () use (&$attempts): string {
                $attempts++;

                return 'rows';
            },
            dropConnection: function (): void {},
            transactionState: $transactionState,
        );
    } catch (LostConnectionException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBeInstanceOf(LostConnectionException::class)
        ->and($caught->getMessage())->toContain('lost earlier')
        ->and($attempts)->toBe(0);
});

it('rethrows any other exception without dropping the connection or retrying', function (): void {
    $attempts = 0;
    $drops = 0;
    $failure = new RuntimeException('syntax error');

    $caught = null;

    try {
        (new LostConnectionRecovery())->run(
            attempt: function () use (&$attempts, $failure): never {
                $attempts++;

                throw $failure;
            },
            dropConnection: function () use (&$drops): void {
                $drops++;
            },
            transactionState: new TransactionState(),
        );
    } catch (RuntimeException $exception) {
        $caught = $exception;
    }

    expect($caught)->toBe($failure)
        ->and($attempts)->toBe(1)
        ->and($drops)->toBe(0);
});
