<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Closure;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Exceptions\LostConnectionException;
use Marko\Database\Exceptions\QueryException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Tests\Fixtures\Retry\ScriptedPdo;
use PDO;
use PDOException;

/**
 * A connection that hands out a fresh scripted PDO per createPdo() call and
 * keeps every PDO it created.
 */
class LostConnectionTestConnection extends PgSqlConnection
{
    /**
     * @var list<ScriptedPdo>
     */
    public array $created = [];

    /**
     * @param Closure(int, ScriptedPdo): void $script Prepares the PDO handed out as the n-th connection (1-based)
     */
    public function __construct(
        DatabaseConfig $config,
        private readonly Closure $script,
    ) {
        parent::__construct($config);
    }

    protected function createPdo(
        string $dsn,
        string $username,
        string $password,
        array $options,
    ): PDO {
        $pdo = new ScriptedPdo();
        $this->created[] = $pdo;
        ($this->script)(count($this->created), $pdo);

        return $pdo;
    }
}

/**
 * @param Closure(int, ScriptedPdo): void $script
 */
function makeLostConnectionConnection(
    Closure $script,
): LostConnectionTestConnection {
    $config = DatabaseConfig::fromArray([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
    ]);

    return new LostConnectionTestConnection($config, $script);
}

function pgsqlLostError(): PDOException
{
    $exception = new PDOException('SQLSTATE[HY000]: General error: 7 no connection to the server');
    $exception->errorInfo = ['HY000', 7, 'no connection to the server'];

    return $exception;
}

/**
 * A script that makes the first connection's next prepare() lose the connection.
 *
 * @return Closure(int, ScriptedPdo): void
 */
function pgsqlLoseFirstPrepare(): Closure
{
    return function (int $number, ScriptedPdo $pdo): void {
        if ($number === 1) {
            $pdo->prepareFailures = [pgsqlLostError()];
        }
    };
}

function pgsqlSyntaxError(): PDOException
{
    $exception = new PDOException('SQLSTATE[42601]: Syntax error: 7 syntax error at or near "SELEC"');
    $exception->errorInfo = ['42601', 7, 'syntax error at or near "SELEC"'];

    return $exception;
}

describe('PgSqlConnection lost connection recovery', function (): void {
    it(
        'reconnects and reruns a query once when the connection is lost outside a transaction and never retries an ordinary query error',
        function (): void {
            $connection = makeLostConnectionConnection(pgsqlLoseFirstPrepare());

            $rows = $connection->query('SELECT 1 AS n');

            expect($rows)->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(2)
                ->and($connection->created[0]->prepareCalls)->toBe(1)
                ->and($connection->created[1]->prepareCalls)->toBe(1);

            $ordinary = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                $pdo->prepareFailures = [pgsqlSyntaxError()];
            });

            $thrown = null;

            try {
                $ordinary->query('SELEC 1');
            } catch (QueryException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(QueryException::class)
                ->not->toBeInstanceOf(LostConnectionException::class)
                ->and($ordinary->created)->toHaveCount(1)
                ->and($ordinary->created[0]->prepareCalls)->toBe(1);
        },
    );

    it(
        'reconnects and reruns execute and prepare once when the connection is lost outside a transaction',
        function (): void {
            $script = pgsqlLoseFirstPrepare();

            $executing = makeLostConnectionConnection($script);
            $affected = $executing->execute('UPDATE items SET name = name');

            $preparing = makeLostConnectionConnection($script);
            $statement = $preparing->prepare('SELECT 1 AS n');
            $statement->execute();

            expect($affected)->toBe(0)
                ->and($executing->created)->toHaveCount(2)
                ->and($executing->created[1]->prepareCalls)->toBe(1)
                ->and($preparing->created)->toHaveCount(2)
                ->and($statement->fetchAll())->toBe([['n' => 1]]);
        },
    );

    it(
        'gives up after the retried statement loses the connection again, leaving the connection ready to reconnect',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number <= 2) {
                    $pdo->prepareFailures = [pgsqlLostError()];
                }
            });

            $thrown = null;

            try {
                $connection->query('SELECT 1 AS n');
            } catch (LostConnectionException $e) {
                $thrown = $e;
            }

            expect($thrown)->toBeInstanceOf(LostConnectionException::class)
                ->and($connection->created)->toHaveCount(2)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(3);
        },
    );

    it(
        'throws LostConnectionException and keeps the transaction level when a statement loses the connection inside a transaction, then refuses further statements and lastInsertId without reconnecting',
        function (): void {
            $connection = makeLostConnectionConnection(pgsqlLoseFirstPrepare());
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
                ->and($connection->created)->toHaveCount(1)
                ->and($connection->created[0]->prepareCalls)->toBe(1);

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]]);
        },
    );

    it(
        'drops the dead connection when a prepared statement loses it so the next transaction reconnects',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->executeFailures = [pgsqlLostError()];
                }
            });
            $statement = $connection->prepare('SELECT 1 AS n');

            expect(fn () => $statement->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeFalse();

            $result = $connection->transaction(fn (): string => 'done');

            expect($result)->toBe('done')
                ->and($connection->created)->toHaveCount(2);

            // A statement prepared on an earlier, already replaced connection must not drop the healthy one.
            $stale = $connection->prepare('SELECT 1 AS n');
            $connection->created[1]->prepareFailures = [pgsqlLostError()];
            $connection->created[1]->executeFailures = [pgsqlLostError()];
            $connection->query('SELECT 1 AS n');

            expect(fn () => $stale->execute())->toThrow(LostConnectionException::class)
                ->and($connection->isConnected())->toBeTrue()
                ->and($connection->created)->toHaveCount(3);

            $connection->beginTransaction();
            $connection->created[1]->executeFailures = [pgsqlLostError()];

            expect(fn () => $stale->execute())->toThrow(LostConnectionException::class)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->transactionLevel())->toBe(1);
        },
    );

    it(
        'refuses commits and savepoints on a lost transaction without reconnecting',
        function (): void {
            $connection = makeLostConnectionConnection(pgsqlLoseFirstPrepare());
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
                ->and($connection->created)->toHaveCount(1)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(2);
        },
    );

    it(
        'marks the transaction lost and closes the level without touching the dead connection when COMMIT or SAVEPOINT loses it',
        function (): void {
            $committing = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->commitFailures = [pgsqlLostError()];
                }
            });
            $committing->beginTransaction();

            expect(fn () => $committing->commit())->toThrow(LostConnectionException::class)
                ->and($committing->transactionLevel())->toBe(0)
                ->and($committing->created[0]->statements)->toBe(['BEGIN', 'COMMIT'])
                ->and($committing->isConnected())->toBeFalse()
                ->and($committing->created)->toHaveCount(1)
                ->and($committing->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($committing->created)->toHaveCount(2);

            $savepointing = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->execFailures = ['SAVEPOINT' => pgsqlLostError()];
                }
            });
            $savepointing->beginTransaction();

            expect(fn () => $savepointing->beginTransaction())->toThrow(LostConnectionException::class)
                ->and($savepointing->transactionLevel())->toBe(1)
                ->and($savepointing->isConnected())->toBeFalse()
                ->and(fn () => $savepointing->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class)
                ->and($savepointing->created)->toHaveCount(1);
        },
    );

    it(
        'rolls back a lost level without reconnecting or sending sql, runs its after rollback callbacks and reconnects on the next statement',
        function (): void {
            $connection = makeLostConnectionConnection(pgsqlLoseFirstPrepare());
            $rolledBack = [];
            $connection->beginTransaction();
            $connection->afterRollback(function () use (&$rolledBack): void {
                $rolledBack[] = 'outer';
            });

            expect(fn () => $connection->query('SELECT 1 AS n'))->toThrow(LostConnectionException::class);

            $connection->rollback();

            expect($connection->transactionLevel())->toBe(0)
                ->and($rolledBack)->toBe(['outer'])
                ->and($connection->created)->toHaveCount(1)
                ->and($connection->created[0]->statements)->toBe(['BEGIN'])
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(2);
        },
    );

    it(
        'closes the level quietly and marks the outer levels lost when ROLLBACK itself loses the connection',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->execFailures = ['ROLLBACK' => pgsqlLostError()];
                }

                if ($number === 2) {
                    $pdo->rollBackFailures = [pgsqlLostError()];
                }
            });
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
                ->and($connection->created)->toHaveCount(2)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(3);
        },
    );

    it(
        'reconnects when beginning an outermost transaction on a lost connection',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->beginFailures = [pgsqlLostError()];
                }
            });

            $connection->beginTransaction();

            expect($connection->transactionLevel())->toBe(1)
                ->and($connection->created)->toHaveCount(2)
                ->and($connection->created[0]->statements)->toBe(['BEGIN'])
                ->and($connection->created[1]->statements)->toBe(['BEGIN']);

            $connection->commit();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->created[1]->statements)->toBe(['BEGIN', 'COMMIT']);
        },
    );

    it(
        'surfaces LostConnectionException from transaction() when the callback swallows a lost connection and continues',
        function (): void {
            $script = pgsqlLoseFirstPrepare();
            $swallowing = makeLostConnectionConnection($script);

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
                ->and($swallowing->created)->toHaveCount(1);

            $throwing = makeLostConnectionConnection($script);

            expect(fn () => $throwing->transaction(fn (): array => $throwing->query('SELECT 1 AS n')))
                ->toThrow(LostConnectionException::class)
                ->and($throwing->transactionLevel())->toBe(0)
                ->and($throwing->created)->toHaveCount(1);
        },
    );

    it(
        'resets a connection whose server is gone without throwing and reconnects on the next statement',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                if ($number === 1) {
                    $pdo->rollBackFailures = [pgsqlLostError()];
                }
            });
            $connection->beginTransaction();

            $connection->reset();

            expect($connection->transactionLevel())->toBe(0)
                ->and($connection->isConnected())->toBeFalse()
                ->and($connection->created)->toHaveCount(1)
                ->and($connection->query('SELECT 1 AS n'))->toBe([['n' => 1]])
                ->and($connection->created)->toHaveCount(2);
        },
    );

    it(
        'rethrows a translated query error from reset when the rollback fails for another reason',
        function (): void {
            $connection = makeLostConnectionConnection(function (int $number, ScriptedPdo $pdo): void {
                $pdo->rollBackFailures = [pgsqlSyntaxError()];
            });
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
