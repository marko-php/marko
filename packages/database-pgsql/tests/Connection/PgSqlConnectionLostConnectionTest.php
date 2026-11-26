<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Closure;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\PgSql\Tests\Fixtures\LostConnection\LossyPdo;
use Marko\Database\PgSql\Tests\Fixtures\LostConnection\LossyPgSqlConnection;

/**
 * @param array<int, Closure(LossyPdo): void> $scripts One script per PDO, in the order they are created
 */
function makeLossyPgSqlConnection(
    array $scripts = [],
): LossyPgSqlConnection {
    return new LossyPgSqlConnection(
        DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'test',
            'username' => 'test',
            'password' => 'test',
        ]),
        $scripts,
    );
}

describe('PgSqlConnection lost connection recovery', function (): void {
    it(
        'reconnects and reruns a query once when the connection is lost outside a transaction and never retries an ordinary query error',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);

            $rows = $connection->query('SELECT 1 AS n');

            expect($rows)->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->pdos[0]->prepareCalls)->toBe(1)
                ->and($connection->pdos[1]->prepareCalls)->toBe(1);

            $ordinary = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::syntaxError()],
            ]);

            $thrown = null;

            try {
                $ordinary->query('SELEC 1');
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
            $scripts = [
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ];

            $executing = makeLossyPgSqlConnection($scripts);
            $affected = $executing->execute('UPDATE items SET name = name');

            $preparing = makeLossyPgSqlConnection($scripts);
            $statement = $preparing->prepare('SELECT 1 AS n');
            $statement->execute();

            expect($affected)->toBe(0)
                ->and($executing->pdos)->toHaveCount(2)
                ->and($executing->pdos[1]->prepareCalls)->toBe(1)
                ->and($preparing->pdos)->toHaveCount(2)
                ->and($statement->fetchAll())->toBe([['n' => 1]]);
        },
    );

    it(
        'gives up after the retried statement loses the connection again, leaving the connection ready to reconnect',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);

            $thrown = null;

            try {
                $connection->query('SELECT 1 AS n');
            } catch (LostConnectionException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(LostConnectionException::class)
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(3);
        },
    );

    it(
        'throws LostConnectionException and keeps the transaction level when a statement loses the connection inside a transaction, then refuses further statements and lastInsertId without reconnecting',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);
            $connection->beginTransaction();

            $attempts = [
                fn () => $connection->query('SELECT 1 AS n'),
                fn () => $connection->query('SELECT 1 AS n'),
                fn () => $connection->execute('UPDATE items SET name = name'),
                fn () => $connection->prepare('SELECT 1 AS n'),
                fn () => $connection->lastInsertId(),
            ];
            $thrown = [];

            foreach ($attempts as $attempt) {
                try {
                    $attempt();
                } catch (LostConnectionException $e) {
                    $thrown[] = $e;
                }
            }

            expect($thrown)->toHaveCount(5)
                ->and($thrown[0]->getMessage())->toContain('inside a transaction')
                ->and($connection->transactionLevel())->toBe(1)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->pdos[0]->prepareCalls)->toBe(1);

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]]);
        },
    );

    it(
        'drops the dead connection when a prepared statement loses it so the next transaction reconnects',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->executeFailures = [LossyPdo::lostConnection()],
            ]);
            $statement = $connection->prepare('SELECT 1 AS n');

            expect(fn () => $statement->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeFalse();

            $result = $connection->transaction(fn (): string => 'done');

            expect($result)->toBe('done')
                ->and($connection->pdos)->toHaveCount(2);

            // A statement prepared on an earlier, already replaced connection must not drop the healthy one.
            $stale = $connection->prepare('SELECT 1 AS n');
            $connection->pdos[1]->prepareFailures = [LossyPdo::lostConnection()];
            $connection->pdos[1]->executeFailures = [LossyPdo::lostConnection()];
            $connection->query('SELECT 1 AS n');

            expect(fn () => $stale->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeTrue()
                ->and($connection->pdos)->toHaveCount(3);

            $connection->beginTransaction();
            $connection->pdos[1]->executeFailures = [LossyPdo::lostConnection()];

            expect(fn () => $stale->execute())->toThrow(LostConnectionException::class)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->transactionLevel())->toBe(1);
        },
    );

    it(
        'refuses commits and savepoints on a lost transaction without reconnecting',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);
            $connection->beginTransaction();
            $connection->beginTransaction();

            expect(fn () => $connection->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(2)
                ->and(fn () => $connection->beginTransaction())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(2)
                ->and(fn () => $connection->commit())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(1)
                ->and(fn () => $connection->commit())->toThrow(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(0)
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'marks the transaction lost and closes the level without touching the dead connection when COMMIT or SAVEPOINT loses it',
        function (): void {
            $committing = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->commitFailures = [LossyPdo::lostConnection()],
            ]);
            $committing->beginTransaction();

            expect(fn () => $committing->commit())->toThrow(LostConnectionException::class)
                ->and($committing->transactionLevel())->toBe(0)
                ->and($committing->pdos[0]->statements)->toBe(['BEGIN', 'COMMIT'])
                ->and($committing->isConnected())->toBeFalse()
                ->and($committing->pdos)->toHaveCount(1)
                ->and($committing->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($committing->pdos)->toHaveCount(2);

            $savepointing = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->execFailures = ['SAVEPOINT' => LossyPdo::lostConnection()],
            ]);
            $savepointing->beginTransaction();

            expect(fn () => $savepointing->beginTransaction())->toThrow(LostConnectionException::class)
                ->and($savepointing->transactionLevel())->toBe(1)
                ->and($savepointing->isConnected())->toBeFalse()
                ->and(fn () => $savepointing->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class)
                ->and($savepointing->pdos)->toHaveCount(1);
        },
    );

    it(
        'rolls back a lost level without reconnecting or sending sql, runs its after rollback callbacks and reconnects on the next statement',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ]);
            $rolledBack = [];
            $connection->beginTransaction();
            $connection->afterRollback(function () use (&$rolledBack): void {
                $rolledBack[] = 'outer';
            });

            expect(fn () => $connection->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class);

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($rolledBack)->toBe(['outer'])
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->pdos[0]->statements)->toBe(['BEGIN'])
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'closes the level quietly and marks the outer levels lost when ROLLBACK itself loses the connection',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->execFailures = ['ROLLBACK' => LossyPdo::lostConnection()],
                fn (LossyPdo $pdo) => $pdo->rollbackFailures = [LossyPdo::lostConnection()],
            ]);
            $rolledBack = [];
            $connection->beginTransaction();
            $connection->beginTransaction();
            $connection->afterRollback(function () use (&$rolledBack, $connection): void {
                $rolledBack[] = $connection->isConnected();
            });

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(1)
                ->and($rolledBack)->toBe([false])
                ->and(fn () => $connection->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class);

            $connection->rollback();

            $connection->beginTransaction();
            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(3);
        },
    );

    it(
        'reconnects when beginning an outermost transaction on a lost connection',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->beginFailures = [LossyPdo::lostConnection()],
            ]);

            $connection->beginTransaction();

            expect($connection->transactionLevel())->toBe(1)
                ->and($connection->pdos)->toHaveCount(2)
                ->and($connection->pdos[0]->statements)->toBe(['BEGIN'])
                ->and($connection->pdos[1]->statements)->toBe(['BEGIN']);

            $connection->commit();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->pdos[1]->statements)->toBe(['BEGIN', 'COMMIT']);
        },
    );

    it(
        'surfaces LostConnectionException from transaction() when the callback swallows a lost connection and continues',
        function (): void {
            $scripts = [
                fn (LossyPdo $pdo) => $pdo->prepareFailures = [LossyPdo::lostConnection()],
            ];
            $swallowing = makeLossyPgSqlConnection($scripts);

            $swallowed = function () use ($swallowing): string {
                try {
                    $swallowing->query('SELECT 1 AS n');
                } catch (LostConnectionException) {
                    // The caller carries on as if nothing happened.
                }

                return 'carried on';
            };

            expect(fn () => $swallowing->transaction($swallowed))->toThrow(LostConnectionException::class)
                ->and($swallowing->transactionLevel())->toBe(0)
                ->and($swallowing->pdos)->toHaveCount(1);

            $throwing = makeLossyPgSqlConnection($scripts);

            expect(fn () => $throwing->transaction(fn (): array => $throwing->query('SELECT 1 AS n')))
                ->toThrow(LostConnectionException::class)
                ->and($throwing->transactionLevel())->toBe(0)
                ->and($throwing->pdos)->toHaveCount(1);
        },
    );

    it(
        'resets a connection whose server is gone without throwing and reconnects on the next statement',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->rollbackFailures = [LossyPdo::lostConnection()],
            ]);
            $connection->beginTransaction();

            $connection->reset();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->pdos)->toHaveCount(1)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->pdos)->toHaveCount(2);
        },
    );

    it(
        'rethrows a translated query error from reset when the rollback fails for another reason',
        function (): void {
            $connection = makeLossyPgSqlConnection([
                fn (LossyPdo $pdo) => $pdo->rollbackFailures = [LossyPdo::syntaxError()],
            ]);
            $connection->beginTransaction();

            $thrown = null;

            try {
                $connection->reset();
            } catch (QueryException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(LostConnectionException::class)
                ->and($connection->transactionLevel())->toBe(0);
        },
    );
});
