<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use ArrayObject;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\PendingAfterCommitInterface;
use Marko\Database\Exceptions\TransactionException;
use Marko\Database\MySql\Connection\MySqlConnection;
use PDO;
use RuntimeException;

/**
 * A MySqlConnection backed by an in-memory SQLite PDO (which supports real
 * transactions and savepoints) that records every transaction statement.
 *
 * @param ArrayObject<int, string> $statements
 */
function makeSavepointMySqlConnection(ArrayObject $statements = new ArrayObject()): MySqlConnection
{
    $config = DatabaseConfig::fromArray([
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
    ]);

    return new class ($config, $statements) extends MySqlConnection
    {
        /**
         * @param ArrayObject<int, string> $statements
         */
        public function __construct(
            DatabaseConfig $config,
            private readonly ArrayObject $statements,
        ) {
            parent::__construct($config);
        }

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            $pdo = new class ('sqlite::memory:', $this->statements) extends PDO
            {
                /**
                 * @param ArrayObject<int, string> $statements
                 */
                public function __construct(
                    string $dsn,
                    private readonly ArrayObject $statements,
                ) {
                    parent::__construct($dsn);
                }

                public function exec(
                    string $statement,
                ): int|false {
                    // SQLite has no SET NAMES; ignore the encoding query.
                    if (str_starts_with($statement, 'SET NAMES')) {
                        return 0;
                    }

                    if (preg_match('/^(SAVEPOINT|RELEASE|ROLLBACK)/', $statement) === 1) {
                        $this->statements->append($statement);
                    }

                    return parent::exec($statement);
                }

                public function beginTransaction(): bool
                {
                    $this->statements->append('BEGIN');

                    return parent::beginTransaction();
                }

                public function commit(): bool
                {
                    $this->statements->append('COMMIT');

                    return parent::commit();
                }

                public function rollBack(): bool
                {
                    $this->statements->append('ROLLBACK');

                    return parent::rollBack();
                }
            };
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE items (name TEXT)');

            return $pdo;
        }
    };
}

/**
 * @return list<string>
 */
function mysqlItemNames(
    MySqlConnection $connection,
): array {
    return array_column($connection->query('SELECT name FROM items ORDER BY rowid'), 'name');
}

describe('MySqlConnection savepoints', function (): void {
    it('opens a savepoint for a nested beginTransaction', function (): void {
        $statements = new ArrayObject();
        $connection = makeSavepointMySqlConnection($statements);

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->beginTransaction();

        expect($statements->getArrayCopy())->toBe(['BEGIN', 'SAVEPOINT marko_sp_1', 'SAVEPOINT marko_sp_2']);
    });

    it('releases the savepoint when a nested level commits', function (): void {
        $statements = new ArrayObject();
        $connection = makeSavepointMySqlConnection($statements);

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->commit();
        $connection->commit();

        expect($statements->getArrayCopy())->toBe(
            ['BEGIN', 'SAVEPOINT marko_sp_1', 'RELEASE SAVEPOINT marko_sp_1', 'COMMIT'],
        );
    });

    it('rolls back to the savepoint when a nested level rolls back', function (): void {
        $statements = new ArrayObject();
        $connection = makeSavepointMySqlConnection($statements);

        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['outer']);
        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['inner']);
        $connection->rollback();
        $connection->commit();

        expect($statements->getArrayCopy())
            ->toBe(['BEGIN', 'SAVEPOINT marko_sp_1', 'ROLLBACK TO SAVEPOINT marko_sp_1', 'COMMIT'])
            ->and(mysqlItemNames($connection))->toBe(['outer']);
    });

    it('reports transactionLevel through nesting and after rollback', function (): void {
        $connection = makeSavepointMySqlConnection();
        $levels = [$connection->transactionLevel()];

        $connection->beginTransaction();
        $levels[] = $connection->transactionLevel();
        $connection->beginTransaction();
        $levels[] = $connection->transactionLevel();
        $connection->rollback();
        $levels[] = $connection->transactionLevel();
        $connection->rollback();
        $levels[] = $connection->transactionLevel();

        expect($levels)->toBe([0, 1, 2, 1, 0])
            ->and($connection->inTransaction())->toBeFalse();
    });

    it('commits nested transaction() calls together', function (): void {
        $connection = makeSavepointMySqlConnection();

        $connection->transaction(function () use ($connection): void {
            $connection->execute('INSERT INTO items (name) VALUES (?)', ['outer']);
            $connection->transaction(function () use ($connection): void {
                $connection->execute('INSERT INTO items (name) VALUES (?)', ['inner']);
            });
        });

        expect(mysqlItemNames($connection))->toBe(['outer', 'inner'])
            ->and($connection->transactionLevel())->toBe(0);
    });

    it('rolls back only the inner work when a nested transaction() failure is caught', function (): void {
        $connection = makeSavepointMySqlConnection();

        $connection->transaction(function () use ($connection): void {
            $connection->execute('INSERT INTO items (name) VALUES (?)', ['outer']);

            try {
                $connection->transaction(function () use ($connection): void {
                    $connection->execute('INSERT INTO items (name) VALUES (?)', ['inner']);

                    throw new RuntimeException('Inner failure');
                });
            } catch (RuntimeException) {
                // The outer work carries on.
            }
        });

        expect(mysqlItemNames($connection))->toBe(['outer']);
    });

    it('rolls back everything when the outer transaction() fails', function (): void {
        $connection = makeSavepointMySqlConnection();

        $run = fn () => $connection->transaction(function () use ($connection): void {
            $connection->execute('INSERT INTO items (name) VALUES (?)', ['outer']);
            $connection->transaction(function () use ($connection): void {
                $connection->execute('INSERT INTO items (name) VALUES (?)', ['inner']);
            });

            throw new RuntimeException('Outer failure');
        });

        expect($run)->toThrow(RuntimeException::class, 'Outer failure')
            ->and(mysqlItemNames($connection))->toBe([])
            ->and($connection->transactionLevel())->toBe(0);
    });

    it('runs after-commit callbacks after the outermost commit only', function (): void {
        $connection = makeSavepointMySqlConnection();
        $log = [];

        $connection->transaction(function () use ($connection, &$log): void {
            $connection->transaction(function () use ($connection, &$log): void {
                $connection->afterCommit(function () use ($connection, &$log): void {
                    $log[] = 'committed at level ' . $connection->transactionLevel();
                });
            });
            $log[] = 'inner returned';
        });

        expect($log)->toBe(['inner returned', 'committed at level 0']);
    });

    it('runs an after-commit callback immediately outside a transaction', function (): void {
        $connection = makeSavepointMySqlConnection();
        $ran = false;

        $connection->afterCommit(function () use (&$ran): void {
            $ran = true;
        });

        expect($ran)->toBeTrue();
    });

    it('does not run after-commit callbacks registered in a rolled-back savepoint', function (): void {
        $connection = makeSavepointMySqlConnection();
        $log = [];

        $connection->transaction(function () use ($connection, &$log): void {
            try {
                $connection->transaction(function () use ($connection, &$log): void {
                    $connection->afterCommit(function () use (&$log): void {
                        $log[] = 'inner commit';
                    });
                    $connection->afterRollback(function () use (&$log): void {
                        $log[] = 'inner rollback';
                    });

                    throw new RuntimeException('Inner failure');
                });
            } catch (RuntimeException) {
                // Swallowed on purpose: only the savepoint rolls back.
            }
            $connection->afterCommit(function () use (&$log): void {
                $log[] = 'outer commit';
            });
        });

        expect($log)->toBe(['inner rollback', 'outer commit']);
    });

    it(
        'runs after-rollback callbacks and skips after-commit callbacks when the transaction rolls back',
        function (): void {
            $connection = makeSavepointMySqlConnection();
            $log = new ArrayObject();

            $run = fn () => $connection->transaction(function () use ($connection, $log): void {
                $connection->afterCommit(function () use ($log): void {
                    $log[] = 'commit';
                });
                $connection->afterRollback(function () use (&$log): void {
                    $log[] = 'rollback';
                });

                throw new RuntimeException('Failure');
            });

            expect($run)->toThrow(RuntimeException::class)
                ->and($log->getArrayCopy())->toBe(['rollback']);
        },
    );

    it('throws TransactionException when committing with no open transaction', function (): void {
        $connection = makeSavepointMySqlConnection();

        expect(fn () => $connection->commit())->toThrow(TransactionException::class, 'No active transaction');
    });

    it('throws TransactionException when rolling back with no open transaction', function (): void {
        $connection = makeSavepointMySqlConnection();

        expect(fn () => $connection->rollback())->toThrow(TransactionException::class, 'No active transaction');
    });

    it('clears transaction state on disconnect so the next transaction starts with BEGIN', function (): void {
        $statements = new ArrayObject();
        $connection = makeSavepointMySqlConnection($statements);

        $connection->beginTransaction();
        $connection->disconnect();
        $connection->beginTransaction();

        expect($connection->transactionLevel())->toBe(1)
            ->and($statements->getArrayCopy())->toBe(['BEGIN', 'BEGIN']);
    });

    it('clears transaction state on reset without running callbacks', function (): void {
        $connection = makeSavepointMySqlConnection();
        $ran = false;

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->afterRollback(function () use (&$ran): void {
            $ran = true;
        });
        $connection->reset();

        expect($connection->transactionLevel())->toBe(0)
            ->and($connection->inTransaction())->toBeFalse()
            ->and($ran)->toBeFalse();
    });

    it('runs pending after-commit callbacks without committing', function (): void {
        $connection = makeSavepointMySqlConnection();
        $log = [];

        $connection->beginTransaction();
        $connection->beginTransaction();
        $connection->afterCommit(function () use (&$log): void {
            $log[] = 'ran';
        });
        $connection->runPendingAfterCommitCallbacks();
        $connection->commit();
        $connection->rollback();

        expect($log)->toBe(['ran'])
            ->and($connection)->toBeInstanceOf(PendingAfterCommitInterface::class)
            ->and($connection->transactionLevel())->toBe(0);
    });
});
