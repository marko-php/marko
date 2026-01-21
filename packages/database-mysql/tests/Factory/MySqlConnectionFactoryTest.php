<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Factory;

use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Factory\MySqlConnectionFactory;
use ReflectionClass;

function createTestConfig(
    array $overrides = [],
): DatabaseConfig {
    $defaults = [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'test_db',
        'username' => 'test_user',
        'password' => 'test_pass',
    ];

    $config = array_merge($defaults, $overrides);

    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
    mkdir($tempDir . '/config', recursive: true);
    file_put_contents(
        $tempDir . '/config/database.php',
        '<?php return ' . var_export($config, true) . ';',
    );

    $databaseConfig = new DatabaseConfig($tempDir);

    // Cleanup
    unlink($tempDir . '/config/database.php');
    rmdir($tempDir . '/config');
    rmdir($tempDir);

    return $databaseConfig;
}

describe('MySqlConnectionFactory', function (): void {
    it('receives DatabaseConfig via constructor injection', function (): void {
        $config = createTestConfig();

        $factory = new MySqlConnectionFactory($config);

        expect($factory)->toBeInstanceOf(MySqlConnectionFactory::class);
    });

    it('creates MySqlConnection with host from config', function (): void {
        $config = createTestConfig(['host' => 'db.example.com']);
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        expect($connection)->toBeInstanceOf(MySqlConnection::class)
            ->and($connection->getDsn())->toContain('host=db.example.com');
    });

    it('creates MySqlConnection with port from config', function (): void {
        $config = createTestConfig(['port' => 3307]);
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        expect($connection->getDsn())->toContain('port=3307');
    });

    it('creates MySqlConnection with database from config', function (): void {
        $config = createTestConfig(['database' => 'myapp_production']);
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        expect($connection->getDsn())->toContain('dbname=myapp_production');
    });

    it('creates MySqlConnection with username from config', function (): void {
        $config = createTestConfig(['username' => 'admin_user']);
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        // Use reflection to verify the private username property
        $reflection = new ReflectionClass($connection);
        $property = $reflection->getProperty('username');

        expect($property->getValue($connection))->toBe('admin_user');
    });

    it('creates MySqlConnection with password from config', function (): void {
        $config = createTestConfig(['password' => 'secret123']);
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        // Use reflection to verify the private password property
        $reflection = new ReflectionClass($connection);
        $property = $reflection->getProperty('password');

        expect($property->getValue($connection))->toBe('secret123');
    });

    it('returns ConnectionInterface from create method', function (): void {
        $config = createTestConfig();
        $factory = new MySqlConnectionFactory($config);

        $connection = $factory->create();

        expect($connection)->toBeInstanceOf(ConnectionInterface::class);
    });
});
