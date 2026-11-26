<?php

declare(strict_types=1);

use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;

function lostConnectionPdoError(
    string $sqlState = 'HY000',
    int $driverCode = 2006,
    string $driverMessage = 'MySQL server has gone away',
): PDOException {
    $exception = new PDOException("SQLSTATE[$sqlState]: General error: $driverCode $driverMessage");
    $exception->errorInfo = [$sqlState, $driverCode, $driverMessage];

    return $exception;
}

describe('LostConnectionException', function (): void {
    it('is a QueryException', function (): void {
        $exception = LostConnectionException::fromDriverError(lostConnectionPdoError(), 'SELECT 1', []);

        expect($exception)->toBeInstanceOf(QueryException::class);
    });

    it('keeps the sql, bindings, sqlstate and previous PDOException from the driver error', function (): void {
        $pdoException = lostConnectionPdoError();

        $exception = LostConnectionException::fromDriverError($pdoException, 'SELECT * FROM t WHERE id = ?', [5]);

        expect($exception->sql())->toBe('SELECT * FROM t WHERE id = ?')
            ->and($exception->bindings())->toBe([5])
            ->and($exception->sqlState())->toBe('HY000')
            ->and($exception->getPrevious())->toBe($pdoException);
    });

    it('names the lost connection in the message and redacts binding values from it', function (): void {
        $pdoException = lostConnectionPdoError(
            driverCode: 7,
            driverMessage: "FATAL:  terminating connection while running 's3cret-token'\n"
                . 'server closed the connection unexpectedly',
        );

        $exception = LostConnectionException::fromDriverError($pdoException, 'SELECT * FROM t WHERE a = ?', [
            's3cret-token',
        ]);

        expect($exception->getMessage())
            ->toStartWith('Database connection lost: ')
            ->toContain('terminating connection')
            ->toContain('[redacted]')
            ->not->toContain('s3cret-token')
            ->not->toContain('server closed the connection');
    });

    it('includes the sql in the context without binding values', function (): void {
        $exception = LostConnectionException::fromDriverError(
            lostConnectionPdoError(),
            'SELECT * FROM t WHERE a = ?',
            ['s3cret-token'],
        );

        expect($exception->getContext())
            ->toContain('SELECT * FROM t WHERE a = ?')
            ->toContain('1 binding(s)')
            ->not->toContain('s3cret-token')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it(
        'builds a transaction variant that keeps the sql, bindings, sqlstate and previous exception',
        function (): void {
            $pdoException = lostConnectionPdoError();
            $lost = LostConnectionException::fromDriverError($pdoException, 'UPDATE t SET a = ?', ['s3cret-token']);

            $exception = LostConnectionException::duringTransaction($lost);

            expect($exception)->toBeInstanceOf(LostConnectionException::class)
                ->not->toBe($lost)
                ->and($exception->sql())->toBe('UPDATE t SET a = ?')
                ->and($exception->bindings())->toBe(['s3cret-token'])
                ->and($exception->sqlState())->toBe('HY000')
                ->and($exception->getPrevious())->toBe($pdoException)
                ->and($exception->getMessage())->toContain('inside a transaction')
                ->not->toContain('s3cret-token')
                ->and($exception->getContext())->toContain('UPDATE t SET a = ?')
                ->not->toContain('s3cret-token');
        },
    );

    it(
        'says in the transaction variant that the transaction must be rolled back and retried and that a lost commit has an unknown outcome',
        function (): void {
            $lost = LostConnectionException::fromDriverError(lostConnectionPdoError(), 'COMMIT', []);

            $suggestion = LostConnectionException::duringTransaction($lost)->getSuggestion();

            expect($suggestion)
                ->toContain('rollback()')
                ->toContain('retry the whole unit of work from the start')
                ->toContain('COMMIT')
                ->toContain('may or may not have completed')
                ->not->toContain('rolled back the transaction');
        },
    );

    it('refuses a statement on an already lost transaction and tells the caller to roll back', function (): void {
        $exception = LostConnectionException::transactionAlreadyLost();

        expect($exception)->toBeInstanceOf(LostConnectionException::class)
            ->and($exception->sql())->toBe('')
            ->and($exception->bindings())->toBeEmpty()
            ->and($exception->sqlState())->toBeNull()
            ->and($exception->getPrevious())->toBeNull()
            ->and($exception->getMessage())->toContain('lost earlier in this transaction')
            ->toContain('rolled back')
            ->and($exception->getSuggestion())->toContain('rollback()')
            ->toContain('reconnects');
    });
});
