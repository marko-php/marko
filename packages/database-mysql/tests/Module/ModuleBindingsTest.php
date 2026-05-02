<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Module;

use Closure;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Config\DatabaseConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Diff\SqlGeneratorInterface;
use Marko\Database\Exceptions\ConfigurationException;
use Marko\Database\Introspection\IntrospectorInterface;
use Marko\Database\MySql\Connection\MySqlConnection;
use Marko\Database\MySql\Query\MySqlQueryBuilderFactory;
use Marko\Database\MySql\Sql\MySqlGenerator;
use Marko\Database\Query\QueryBuilderFactoryInterface;

describe('MySQL module.php bindings', function (): void {
    it('binds ConnectionInterface to MySqlConnection class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        // Verify bindings key exists and contains ConnectionInterface
        expect($moduleConfig)->toHaveKey('bindings')
            ->and($moduleConfig['bindings'])->toHaveKey(ConnectionInterface::class)
            ->and($moduleConfig['bindings'][ConnectionInterface::class])->toBe(MySqlConnection::class);
    });

    it('binds SqlGeneratorInterface to MySqlGenerator class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        expect($moduleConfig['bindings'])->toHaveKey(SqlGeneratorInterface::class)
            ->and($moduleConfig['bindings'][SqlGeneratorInterface::class])->toBe(MySqlGenerator::class);
    });

    it('binds IntrospectorInterface via closure (requires database name)', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        // IntrospectorInterface still uses a closure because it needs the database name
        expect($moduleConfig['bindings'])->toHaveKey(IntrospectorInterface::class)
            ->and($moduleConfig['bindings'][IntrospectorInterface::class])->toBeInstanceOf(Closure::class);
    });

    it('binds QueryBuilderFactoryInterface to MySqlQueryBuilderFactory class', function (): void {
        $modulePath = dirname(__DIR__, 2);
        $moduleConfig = require $modulePath . '/module.php';

        expect($moduleConfig['bindings'])->toHaveKey(QueryBuilderFactoryInterface::class)
            ->and($moduleConfig['bindings'][QueryBuilderFactoryInterface::class])->toBe(MySqlQueryBuilderFactory::class);
    });

    it('throws ConfigurationException when config file missing', function (): void {
        // Create temp directory WITHOUT config
        $tempDir = sys_get_temp_dir() . '/marko_mysql_noconfig_' . bin2hex(random_bytes(8));
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
