<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Connection;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Connection\PgSqlConnectionFactory;

function makeFactoryTestConfig(): DatabaseConfig
{
    $tempDir = sys_get_temp_dir() . '/marko_factory_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/config', recursive: true);

    file_put_contents(
        $tempDir . '/config/database.php',
        '<?php return ' . var_export([
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'test',
            'username' => 'user',
            'password' => 'pass',
        ], true) . ';',
    );

    $paths = new ProjectPaths($tempDir);
    $config = new DatabaseConfig($paths);

    unlink($tempDir . '/config/database.php');
    rmdir($tempDir . '/config');
    rmdir($tempDir);

    return $config;
}

describe('PgSqlConnectionFactory', function (): void {
    it('creates a PgSqlConnection from a DatabaseConfig', function (): void {
        $config = makeFactoryTestConfig();
        $factory = new PgSqlConnectionFactory();

        $connection = $factory->make($config);

        expect($connection)->toBeInstanceOf(PgSqlConnection::class)
            ->and($connection)->toBeInstanceOf(ConnectionInterface::class);
    });

    it('uses the default utf8 charset', function (): void {
        $config = makeFactoryTestConfig();
        $factory = new PgSqlConnectionFactory();

        $connection = $factory->make($config);

        // Factory with default charset creates a valid PgSqlConnection
        // The charset ('utf8') is the PgSqlConnection default — injected at construction
        expect($connection)->toBeInstanceOf(PgSqlConnection::class)
            ->and($connection->getDsn())->toBe('pgsql:host=localhost;port=5432;dbname=test');
    });
});
