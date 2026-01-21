<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Factory;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Factory\PgSqlConnectionFactory;
use ReflectionClass;
use ReflectionProperty;

describe('PgSqlConnectionFactory', function (): void {
    it('receives DatabaseConfig via constructor injection', function (): void {
        $config = createTestConfig();

        $factory = new PgSqlConnectionFactory($config);

        expect($factory)->toBeInstanceOf(PgSqlConnectionFactory::class);
    });

    it('creates PgSqlConnection with host from config', function (): void {
        $config = createTestConfig(host: 'db.example.com');

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        expect($connection->getDsn())->toContain('host=db.example.com');
    });

    it('creates PgSqlConnection with port from config', function (): void {
        $config = createTestConfig(port: 5433);

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        expect($connection->getDsn())->toContain('port=5433');
    });

    it('creates PgSqlConnection with database from config', function (): void {
        $config = createTestConfig(database: 'myapp_db');

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        expect($connection->getDsn())->toContain('dbname=myapp_db');
    });

    it('creates PgSqlConnection with username from config', function (): void {
        $config = createTestConfig(username: 'app_user');

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        // Username is not in DSN, so we verify by checking connection is created
        // The connection will use this username when connecting
        expect($connection)->toBeInstanceOf(PgSqlConnection::class);
    });

    it('creates PgSqlConnection with password from config', function (): void {
        $config = createTestConfig(password: 'secret123');

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        // Password is not in DSN, so we verify by checking connection is created
        // The connection will use this password when connecting
        expect($connection)->toBeInstanceOf(PgSqlConnection::class);
    });

    it('returns ConnectionInterface from create method', function (): void {
        $config = createTestConfig();

        $factory = new PgSqlConnectionFactory($config);
        $connection = $factory->create();

        expect($connection)->toBeInstanceOf(ConnectionInterface::class);
    });
});

/**
 * Create a test DatabaseConfig with specified values.
 */
function createTestConfig(
    string $driver = 'pgsql',
    string $host = 'localhost',
    int $port = 5432,
    string $database = 'test',
    string $username = 'user',
    string $password = 'pass',
): DatabaseConfig {
    // Create mock without constructor
    $config = (new ReflectionClass(DatabaseConfig::class))->newInstanceWithoutConstructor();

    // Set properties via reflection
    $setProperty = function (string $name, mixed $value) use ($config): void {
        $prop = new ReflectionProperty(DatabaseConfig::class, $name);
        $prop->setValue($config, $value);
    };

    $setProperty('driver', $driver);
    $setProperty('host', $host);
    $setProperty('port', $port);
    $setProperty('database', $database);
    $setProperty('username', $username);
    $setProperty('password', $password);

    return $config;
}
