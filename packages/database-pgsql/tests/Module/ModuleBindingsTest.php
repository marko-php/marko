<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Module;

use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\PgSql\Connection\PgSqlConnection;
use Marko\Database\PgSql\Introspection\PgSqlIntrospector;
use Marko\Database\PgSql\Sql\PgSqlGenerator;

describe('PostgreSQL module.php bindings', function (): void {
    it('binds ConnectionInterface to PgSqlConnection class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        // Verify bindings key exists and contains ConnectionInterface
        expect($moduleConfig)->toHaveKey('bindings')
            ->and($moduleConfig['bindings'])->toHaveKey(ConnectionInterface::class)
            ->and($moduleConfig['bindings'][ConnectionInterface::class])->toBe(PgSqlConnection::class);
    });

    it('binds SqlGeneratorInterface to PgSqlGenerator class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        expect($moduleConfig['bindings'])->toHaveKey(SqlGeneratorInterface::class)
            ->and($moduleConfig['bindings'][SqlGeneratorInterface::class])->toBe(PgSqlGenerator::class);
    });

    it('binds IntrospectorInterface to PgSqlIntrospector class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        expect($moduleConfig['bindings'])->toHaveKey(IntrospectorInterface::class)
            ->and($moduleConfig['bindings'][IntrospectorInterface::class])->toBe(PgSqlIntrospector::class);
    });

    it('throws ConfigurationException when config file missing', function (): void {
        // Create temp directory WITHOUT config
        $tempDir = sys_get_temp_dir() . '/marko_pgsql_noconfig_' . bin2hex(random_bytes(8));
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
