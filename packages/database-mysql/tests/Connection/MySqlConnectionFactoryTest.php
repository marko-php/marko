<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Connection;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Connection\MySqlConnectionFactory;

describe('MySqlConnectionFactory', function (): void {
    it('creates a MySqlConnection from a DatabaseConfig', function (): void {
        $config = createTestDatabaseConfig();
        $factory = new MySqlConnectionFactory();

        $connection = $factory->make($config);

        expect($connection)->toBeInstanceOf(MySqlConnection::class)
            ->and($connection)->toBeInstanceOf(ConnectionInterface::class);
    });

    it('uses the default utf8mb4 charset', function (): void {
        $config = createTestDatabaseConfig();
        $factory = new MySqlConnectionFactory();

        $connection = $factory->make($config);

        // The default charset is utf8mb4 — verified by the DSN containing charset=utf8mb4
        expect($connection)->toBeInstanceOf(MySqlConnection::class)
            ->and($connection->getDsn())->toContain('charset=utf8mb4');
    });
});
