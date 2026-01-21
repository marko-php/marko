<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Module;

use Closure;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Factory\PgSqlConnectionFactory;

describe('PostgreSQL module.php bindings', function (): void {
    it('binds ConnectionInterface to factory-created PgSqlConnection in pgsql driver', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        // Verify bindings key exists and contains ConnectionInterface
        expect($moduleConfig)->toHaveKey('bindings')
            ->and($moduleConfig['bindings'])->toHaveKey(ConnectionInterface::class);

        // Verify the binding is a closure
        $binding = $moduleConfig['bindings'][ConnectionInterface::class];
        expect($binding)->toBeInstanceOf(Closure::class);

        // Create a mock container that returns a factory
        $factory = $this->createMock(PgSqlConnectionFactory::class);
        $connection = $this->createMock(PgSqlConnection::class);

        $factory->expects($this->once())
            ->method('create')
            ->willReturn($connection);

        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(PgSqlConnectionFactory::class)
            ->willReturn($factory);

        // Execute the binding closure
        $result = $binding($container);

        expect($result)->toBe($connection);
    });

    it('resolves ConnectionInterface to working connection when config exists', function (): void {
        // Create temp directory with config
        $tempDir = sys_get_temp_dir() . '/marko_pgsql_test_' . uniqid();
        mkdir($tempDir . '/config', recursive: true);
        file_put_contents(
            $tempDir . '/config/database.php',
            '<?php return ' . var_export([
                'driver' => 'pgsql',
                'host' => 'localhost',
                'port' => 5432,
                'database' => 'test_db',
                'username' => 'test_user',
                'password' => 'test_pass',
            ], true) . ';',
        );

        try {
            // Create real DatabaseConfig via ProjectPaths
            $paths = new ProjectPaths($tempDir);
            $config = new DatabaseConfig($paths);

            // Create real factory
            $factory = new PgSqlConnectionFactory($config);

            // Create container with factory instance
            $container = new Container();
            $container->instance(PgSqlConnectionFactory::class, $factory);

            // Get the binding closure from module.php
            $modulePath = dirname(__DIR__, 2);
            $moduleConfig = require $modulePath . '/module.php';
            $binding = $moduleConfig['bindings'][ConnectionInterface::class];

            // Execute the binding
            $result = $binding($container);

            expect($result)->toBeInstanceOf(PgSqlConnection::class)
                ->and($result)->toBeInstanceOf(ConnectionInterface::class)
                ->and($result->getDsn())->toContain('pgsql:')
                ->and($result->getDsn())->toContain('host=localhost')
                ->and($result->getDsn())->toContain('dbname=test_db');
        } finally {
            // Cleanup
            unlink($tempDir . '/config/database.php');
            rmdir($tempDir . '/config');
            rmdir($tempDir);
        }
    });

    it('throws ConfigurationException when config file missing', function (): void {
        // Create temp directory WITHOUT config
        $tempDir = sys_get_temp_dir() . '/marko_pgsql_noconfig_' . uniqid();
        mkdir($tempDir, recursive: true);

        try {
            // This should throw ConfigurationException when DatabaseConfig is instantiated
            $paths = new ProjectPaths($tempDir);
            expect(fn () => new DatabaseConfig($paths))
                ->toThrow(ConfigurationException::class, 'Database configuration file not found');
        } finally {
            // Cleanup
            rmdir($tempDir);
        }
    });
});
