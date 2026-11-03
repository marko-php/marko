<?php

declare(strict_types=1);

namespace Marko\Database\Sqlite\Tests\Connection;

use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LockTimeoutException;
use Marko\Database\Sqlite\Tests\Fixtures\ScriptedSqliteConnection;
use PDOException;

function busySnapshot(): PDOException
{
    $failure = new PDOException('database is locked');
    $failure->errorInfo = ['HY000', 517, 'database is locked'];

    return $failure;
}

function busyTimeout(): PDOException
{
    $failure = new PDOException('database is locked');
    $failure->errorInfo = ['HY000', 5, 'database is locked'];

    return $failure;
}

describe('SqliteConnection transactions', function (): void {
    it(
        'opens the outermost transaction with BEGIN IMMEDIATE and commits with COMMIT without using PDO transaction methods',
        function (): void {
            $connection = new ScriptedSqliteConnection();

            expect($connection)->toBeInstanceOf(TransactionInterface::class)
                ->and($connection->inTransaction())->toBeFalse();

            $connection->beginTransaction();
            $level = $connection->transactionLevel();
            $connection->execute('INSERT INTO items (name) VALUES (?)', ['a']);
            $connection->commit();

            expect($level)->toBe(1)
                ->and($connection->inTransaction())->toBeFalse()
                ->and($connection->scripted->statements)->toBe(['BEGIN IMMEDIATE', 'COMMIT'])
                ->and($connection->query('SELECT name FROM items'))->toBe([['name' => 'a']]);
        },
    );

    it('uses a savepoint for a nested transaction and releases it on commit', function (): void {
        $connection = new ScriptedSqliteConnection();

        $connection->beginTransaction();
        $connection->beginTransaction();
        $level = $connection->transactionLevel();
        $connection->commit();
        $connection->commit();

        expect($level)->toBe(2)
            ->and($connection->scripted->statements)->toBe([
                'BEGIN IMMEDIATE',
                'SAVEPOINT marko_sp_1',
                'RELEASE SAVEPOINT marko_sp_1',
                'COMMIT',
            ]);
    });

    it('rolls back to the savepoint when a nested transaction rolls back', function (): void {
        $connection = new ScriptedSqliteConnection();

        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['outer']);
        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['inner']);
        $connection->rollback();
        $connection->commit();

        expect($connection->scripted->statements)->toBe([
            'BEGIN IMMEDIATE',
            'SAVEPOINT marko_sp_1',
            'ROLLBACK TO SAVEPOINT marko_sp_1',
            'COMMIT',
        ])->and($connection->query('SELECT name FROM items'))->toBe([['name' => 'outer']]);
    });

    it(
        'runs after-commit callbacks only after the outermost commit and after-rollback callbacks on rollback',
        function (): void {
            $connection = new ScriptedSqliteConnection();
            $log = [];

            expect($connection)->toBeInstanceOf(PendingAfterCommitInterface::class);

            $connection->beginTransaction();
            $connection->afterCommit(function () use (&$log): void {
                $log[] = 'commit';
            });
            $connection->beginTransaction();
            $connection->commit();
            $beforeOutermost = $log;
            $connection->commit();

            $connection->beginTransaction();
            $connection->afterCommit(function () use (&$log): void {
                $log[] = 'discarded';
            });
            $connection->afterRollback(function () use (&$log): void {
                $log[] = 'rollback';
            });
            $connection->rollback();

            expect($beforeOutermost)->toBeEmpty()
                ->and($log)->toBe(['commit', 'rollback']);
        },
    );

    it(
        'retries a transaction that fails with SQLITE_BUSY_SNAPSHOT and does not retry a LockTimeoutException',
        function (): void {
            $connection = new ScriptedSqliteConnection();
            $runs = 0;

            $result = $connection->transaction(function () use ($connection, &$runs): string {
                $runs++;

                if ($runs === 1) {
                    $connection->scripted->execFailures['COMMIT'] = busySnapshot();
                }

                return 'done';
            }, attempts: 3, backoff: 0);

            expect($result)->toBe('done')
                ->and($runs)->toBe(2)
                ->and($connection->scripted->statements)->toBe([
                    'BEGIN IMMEDIATE',
                    'COMMIT',
                    'ROLLBACK',
                    'BEGIN IMMEDIATE',
                    'COMMIT',
                ]);

            $connection = new ScriptedSqliteConnection();
            $runs = 0;
            $connection->scripted->execFailures['BEGIN'] = busyTimeout();

            expect(fn () => $connection->transaction(function () use (&$runs): void {
                $runs++;
            }, attempts: 3, backoff: 0))->toThrow(LockTimeoutException::class)
                ->and($runs)->toBe(0)
                ->and($connection->inTransaction())->toBeFalse();
        },
    );

    it('closes the level as rolled back when sqlite has already ended the transaction', function (): void {
        $connection = new ScriptedSqliteConnection();
        $rolledBack = false;

        $connection->beginTransaction();
        $connection->afterRollback(function () use (&$rolledBack): void {
            $rolledBack = true;
        });
        $connection->execute('ROLLBACK');
        $connection->rollback();

        expect($rolledBack)->toBeTrue()
            ->and($connection->inTransaction())->toBeFalse();
    });

    it('rolls back an abandoned transaction and clears transaction state on reset', function (): void {
        $connection = new ScriptedSqliteConnection();
        $ran = false;

        expect($connection)->toBeInstanceOf(ResettableInterface::class);

        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['x']);
        $connection->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $connection->reset();

        expect($connection->inTransaction())->toBeFalse()
            ->and($ran)->toBeFalse()
            ->and($connection->scripted->statements)->toBe(['BEGIN IMMEDIATE', 'ROLLBACK'])
            ->and($connection->query('SELECT name FROM items'))->toBeEmpty();

        $unconnected = new ScriptedSqliteConnection();
        $unconnected->reset();

        expect($unconnected->isConnected())->toBeFalse();
    });
});
