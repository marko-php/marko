<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use Closure;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\MySql\Tests\Fixtures\LostConnection\LossyMySqlConnection;
use Marko\Database\MySql\Tests\Fixtures\LostConnection\LossyPdo;

/**
 * @param array<int, Closure(LossyPdo): void> $scripts One script per PDO, in the order they are created
 */
function makeLossyMySqlConnection(
    array $scripts = [],
): LossyMySqlConnection {
    return new LossyMySqlConnection(
        DatabaseConfig::fromArray([
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'database' => 'test',
            'username' => 'test',
            'password' => 'test',
        ]),
        $scripts,
    );
}

describe('MySqlConnection lost connection recovery', function (): void {
    it(
        'reconnects and reruns a query once when the connection is lost outside a transaction and never retries an ordinary query error',
        function (): void {
            $connection = makeLossyMySqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);

            $rows = $connection->query('SELECT 1 AS one');

            expect($rows)->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->pdos[0]->prepareCalls)->toBe(1)
                ->and($connection->pdos[1]->prepareCalls)->toBe(1);

            $ordinary = makeLossyMySqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::syntaxError()],
            ]);

            $thrown = null;

            try {
                $ordinary->query('SELEKT 1');
            } catch (QueryException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(LostConnectionException::class)
                ->and($ordinary->pdos)->toHaveCount(1)
                ->and($ordinary->pdos[0]->prepareCalls)->toBe(1);
        },
    );

    it(
        'reconnects and reruns execute and prepare once when the connection is lost outside a transaction',
        function (): void {
            $connection = makeLossyMySqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);

            $affected = $connection->execute('INSERT INTO items (name) VALUES (?)', ['a']);

            expect($affected)->toBe(1)
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->pdos[1]->prepareCalls)->toBe(1);

            $connection->pdos[1]->prepareFailures = [LossyPdo::lostConnection()];

            $statement = $connection->prepare('SELECT name FROM items');

            expect($connection->pdos)->toHaveCount(3)
                ->and($connection->pdos[2]->prepareCalls)->toBe(1)
                ->and($statement->execute())->toBeTrue();
        },
    );

    it(
        'gives up after the retried statement loses the connection again, leaving the connection ready to reconnect',
        function (): void {
            $connection = makeLossyMySqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);

            $thrown = null;

            try {
                $connection->query('SELECT 1 AS one');
            } catch (LostConnectionException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(LostConnectionException::class)
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(3);
        },
    );

    it(
        'throws LostConnectionException and keeps the transaction level when a statement loses the connection inside a transaction, then refuses further statements and lastInsertId without reconnecting',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $connection->beginTransaction();
            $connection->pdos[0]->prepareFailures = [LossyPdo::lostConnection()];

            $first = null;

            try {
                $connection->query('SELECT 1 AS one');
            } catch (LostConnectionException $e) {
                $first = $e;
            }

            $refusals = [];

            foreach (
                [
                    fn () => $connection->query('SELECT 1 AS one'),
                    fn () => $connection->execute('DELETE FROM items'),
                    fn () => $connection->prepare('SELECT 1 AS one'),
                    fn () => $connection->lastInsertId(),
                ] as $statement
            ) {
                try {
                    $statement();
                } catch (LostConnectionException $e) {
                    $refusals[] = $e;
                }
            }

            expect($first)->toBeInstanceOf(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(1)
                ->and($refusals)->toHaveCount(4)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->pdos[0]->prepareCalls)->toBe(1);
        },
    );

    it(
        'drops the dead connection when a prepared statement loses it so the next transaction reconnects',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $statement = $connection->prepare('SELECT 1 AS one');
            $connection->pdos[0]->executeFailures = [LossyPdo::lostConnection()];

            expect(fn () => $statement->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeFalse();

            $connection->beginTransaction();

            expect($connection->pdos)->toHaveCount(2)
                ->and($connection->transactionLevel())->toBe(1);

            $connection->pdos[0]->executeFailures = [LossyPdo::lostConnection()];

            expect(fn () => $statement->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeTrue()
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'refuses commits and savepoints on a lost transaction without reconnecting',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $connection->beginTransaction();
            $connection->beginTransaction();
            $connection->pdos[0]->prepareFailures = [LossyPdo::lostConnection()];

            expect(fn () => $connection->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class)
                ->and(fn () => $connection->beginTransaction())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(2)
                ->and(fn () => $connection->commit())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(1)
                ->and(fn () => $connection->commit())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(0)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->isConnected())->toBeFalse();
        },
    );

    it(
        'marks the transaction lost and closes the level without touching the dead connection when COMMIT or SAVEPOINT loses it',
        function (): void {
            $commit = makeLossyMySqlConnection();
            $commit->beginTransaction();
            $commit->pdos[0]->commitFailures = [LossyPdo::lostConnection()];

            expect(fn () => $commit->commit())->toThrow(LostConnectionException::class)
                ->and($commit->transactionLevel())->toBe(0)
                ->and($commit->pdos[0]->statements)->not->toContain('ROLLBACK')
                ->and($commit->isConnected())->toBeFalse()
                ->and($commit->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($commit->pdos)->toHaveCount(2);

            $release = makeLossyMySqlConnection();
            $release->beginTransaction();
            $release->beginTransaction();
            $release->pdos[0]->execFailures = ['RELEASE' => LossyPdo::lostConnection()];

            expect(fn () => $release->commit())->toThrow(LostConnectionException::class)
                ->and($release->transactionLevel())->toBe(1)
                ->and($release->isConnected())->toBeFalse()
                ->and(fn () => $release->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class)
                ->and($release->pdos)->toHaveCount(1);

            $savepoint = makeLossyMySqlConnection();
            $savepoint->beginTransaction();
            $savepoint->pdos[0]->execFailures = ['SAVEPOINT' => LossyPdo::lostConnection()];

            expect(fn () => $savepoint->beginTransaction())->toThrow(LostConnectionException::class)
                ->and($savepoint->transactionLevel())->toBe(1)
                ->and($savepoint->isConnected())->toBeFalse()
                ->and(fn () => $savepoint->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class)
                ->and($savepoint->pdos)->toHaveCount(1);
        },
    );

    it(
        'rolls back a lost level without reconnecting or sending sql, runs its after rollback callbacks and reconnects on the next statement',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $connection->beginTransaction();
            $connection->beginTransaction();
            $rolledBack = [];
            $connection->afterRollback(function () use (&$rolledBack): void {
                $rolledBack[] = 'inner';
            });
            $connection->pdos[0]->prepareFailures = [LossyPdo::lostConnection()];

            expect(fn () => $connection->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class);

            $connection->rollback();

            expect($rolledBack)->toBe(['inner'])
                ->and($connection->transactionLevel())->toBe(1)
                ->and(fn () => $connection->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class);

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->pdos[0]->statements)->not->toContain('ROLLBACK')
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'closes the level quietly when ROLLBACK itself loses the connection, keeping outer levels lost',
        function (): void {
            $outermost = makeLossyMySqlConnection();
            $outermost->beginTransaction();
            $rolledBack = 0;
            $outermost->afterRollback(function () use (&$rolledBack): void {
                $rolledBack++;
            });
            $outermost->pdos[0]->rollbackFailures = [LossyPdo::lostConnection()];

            $outermost->rollback();

            expect($rolledBack)->toBe(1)
                ->and($outermost->transactionLevel())->toBe(0)
                ->and($outermost->isConnected())->toBeFalse()
                ->and($outermost->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($outermost->pdos)->toHaveCount(2);

            $nested = makeLossyMySqlConnection();
            $nested->beginTransaction();
            $nested->beginTransaction();
            $nested->pdos[0]->execFailures = ['ROLLBACK' => LossyPdo::lostConnection()];

            $nested->rollback();

            expect($nested->transactionLevel())->toBe(1)
                ->and($nested->isConnected())->toBeFalse()
                ->and(fn () => $nested->query('SELECT 1 AS one'))->toThrow(LostConnectionException::class)
                ->and($nested->pdos)->toHaveCount(1);

            $nested->rollback();

            expect($nested->transactionLevel())->toBe(0)
                ->and($nested->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($nested->pdos)->toHaveCount(2);
        },
    );

    it(
        'reconnects when beginning an outermost transaction on a lost connection',
        function (): void {
            $connection = makeLossyMySqlConnection([
                fn (LossyPdo $pdo) => $pdo->beginFailures = [LossyPdo::lostConnection()],
            ]);

            $connection->beginTransaction();

            expect($connection->transactionLevel())->toBe(1)
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->pdos[1]->statements)->toBe(['BEGIN'])
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'surfaces LostConnectionException from transaction() when the callback swallows a lost connection and continues',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $rolledBack = 0;

            $thrown = null;

            try {
                $connection->transaction(function () use ($connection, &$rolledBack): string {
                    $connection->afterRollback(function () use (&$rolledBack): void {
                        $rolledBack++;
                    });
                    $connection->pdos[0]->prepareFailures = [LossyPdo::lostConnection()];

                    try {
                        $connection->query('SELECT 1 AS one');
                    } catch (LostConnectionException) {
                        // swallowed: the callback carries on as though nothing happened
                    }

                    return 'done';
                });
            } catch (LostConnectionException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(LostConnectionException::class)
                ->and($rolledBack)->toBe(1)
                ->and($connection->transactionLevel())->toBe(0)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'resets a connection whose server is gone without throwing and reconnects on the next statement',
        function (): void {
            $connection = makeLossyMySqlConnection();
            $connection->beginTransaction();
            $connection->pdos[0]->rollbackFailures = [LossyPdo::lostConnection()];

            $connection->reset();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->query('SELECT 1 AS one'))->toBe([['one' => 1]])
                ->and($connection->pdos)->toHaveCount(2);

            $failing = makeLossyMySqlConnection();
            $failing->beginTransaction();
            $failing->pdos[0]->rollbackFailures = [LossyPdo::syntaxError()];

            expect(fn () => $failing->reset())->toThrow(QueryException::class)
                ->and($failing->pdos)->toHaveCount(1);
        },
    );
});
