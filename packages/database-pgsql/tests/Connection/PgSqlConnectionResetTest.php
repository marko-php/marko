<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use PDO;

/**
 * A PgSqlConnection backed by an in-memory SQLite PDO, which supports real
 * transactions, so reset() can be exercised without a PostgreSQL server.
 */
function makeResettablePgSqlConnection(
    int &$pdoCreations = 0,
): PgSqlConnection {
    $config = DatabaseConfig::fromArray([
        'driver' => 'pgsql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
    ]);

    return new class ($config, $pdoCreations) extends PgSqlConnection
    {
        public function __construct(
            DatabaseConfig $config,
            private int &$pdoCreations,
        ) {
            parent::__construct($config);
        }

        protected function createPdo(
            string $dsn,
            string $username,
            string $password,
            array $options,
        ): PDO {
            $this->pdoCreations++;

            $pdo = new class ('sqlite::memory:') extends PDO
            {
                public function exec(
                    string $statement,
                ): int|false {
                    // SQLite has no SET NAMES or SET TIME ZONE; ignore the session statements.
                    return str_starts_with($statement, 'SET ') ? 0 : parent::exec($statement);
                }
            };
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE items (name TEXT)');

            return $pdo;
        }
    };
}

describe('PgSqlConnection reset', function (): void {
    it('implements ResettableInterface', function (): void {
        expect(makeResettablePgSqlConnection())->toBeInstanceOf(ResettableInterface::class);
    });

    it('rolls back an open transaction on reset', function (): void {
        $connection = makeResettablePgSqlConnection();
        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['abandoned']);

        $connection->reset();

        expect($connection->inTransaction())->toBeFalse()
            ->and($connection->query('SELECT name FROM items'))->toBe([]);
    });

    it('does nothing on reset when no transaction is open', function (): void {
        $connection = makeResettablePgSqlConnection();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['committed']);

        $connection->reset();

        expect($connection->inTransaction())->toBeFalse()
            ->and($connection->isConnected())->toBeTrue()
            ->and($connection->query('SELECT name FROM items'))->toBe([['name' => 'committed']]);
    });

    it('does not open a connection on reset when never connected', function (): void {
        $pdoCreations = 0;
        $connection = makeResettablePgSqlConnection($pdoCreations);

        $connection->reset();

        expect($connection->isConnected())->toBeFalse()
            ->and($pdoCreations)->toBe(0);
    });
});
