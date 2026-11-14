<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use Marko\Core\Contracts\ResettableInterface;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\MySql\Connection\MySqlConnection;
use PDO;

/**
 * A MySqlConnection backed by an in-memory SQLite PDO, which supports real
 * transactions, so reset() can be exercised without a MySQL server.
 */
function makeResettableMySqlConnection(
    int &$pdoCreations = 0,
): MySqlConnection {
    $config = DatabaseConfig::fromArray([
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'test',
        'username' => 'test',
        'password' => 'test',
    ]);

    return new class ($config, $pdoCreations) extends MySqlConnection
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

            $pdo = new PDO('sqlite::memory:');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec('CREATE TABLE items (name TEXT)');

            return $pdo;
        }
    };
}

describe('MySqlConnection reset', function (): void {
    it('implements ResettableInterface', function (): void {
        expect(makeResettableMySqlConnection())->toBeInstanceOf(ResettableInterface::class);
    });

    it('rolls back an open transaction on reset', function (): void {
        $connection = makeResettableMySqlConnection();
        $connection->beginTransaction();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['abandoned']);

        $connection->reset();

        expect($connection->inTransaction())->toBeFalse()
            ->and($connection->query('SELECT name FROM items'))->toBe([]);
    });

    it('does nothing on reset when no transaction is open', function (): void {
        $connection = makeResettableMySqlConnection();
        $connection->execute('INSERT INTO items (name) VALUES (?)', ['committed']);

        $connection->reset();

        expect($connection->inTransaction())->toBeFalse()
            ->and($connection->isConnected())->toBeTrue()
            ->and($connection->query('SELECT name FROM items'))->toBe([['name' => 'committed']]);
    });

    it('does not open a connection on reset when never connected', function (): void {
        $pdoCreations = 0;
        $connection = makeResettableMySqlConnection($pdoCreations);

        $connection->reset();

        expect($connection->isConnected())->toBeFalse()
            ->and($pdoCreations)->toBe(0);
    });
});
