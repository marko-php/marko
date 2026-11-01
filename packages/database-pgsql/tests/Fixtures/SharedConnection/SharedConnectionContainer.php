<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\SharedConnection;

use Marko\Clock\SystemClock;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Database\Config\DatabaseConfig;
use Psr\Clock\ClockInterface;

/**
 * Builds a container from the real marko/database and marko/database-pgsql
 * module manifests, mirroring Application::initialize(): every module's
 * static bindings are registered first, then boot callbacks run in order.
 */
class SharedConnectionContainer
{
    /**
     * @param list<ModuleManifest> $extraModules Modules registered after the database modules
     */
    public static function build(
        DatabaseConfig $config,
        array $extraModules = [],
    ): Container {
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(Container::class, $container);
        $container->instance(DatabaseConfig::class, $config);
        $container->instance(ClockInterface::class, new SystemClock());
        // marko/core binds the default sleeper in a real application.
        $container->bind(SleeperInterface::class, UsleepSleeper::class);
        // marko/core binds the dispatcher in a real application.
        $container->instance(EventDispatcherInterface::class, new class () implements EventDispatcherInterface
        {
            public function dispatch(Event $event): void {}
        });
        $container->instance(
            ProjectPaths::class,
            new ProjectPaths(sys_get_temp_dir() . '/marko_shared_connection_' . bin2hex(random_bytes(8))),
        );

        $packagesPath = dirname(__DIR__, 4);
        $modules = [
            self::manifest('marko/database', $packagesPath . '/database/module.php'),
            self::manifest('marko/database-pgsql', $packagesPath . '/database-pgsql/module.php'),
            ...$extraModules,
        ];

        $registry = new BindingRegistry($container);

        foreach ($modules as $module) {
            $registry->registerModule($module);
        }

        foreach ($modules as $module) {
            if ($module->boot !== null) {
                $container->call($module->boot);
            }
        }

        return $container;
    }

    public static function manifest(
        string $name,
        string $moduleFile,
    ): ModuleManifest {
        $moduleConfig = require $moduleFile;

        return new ModuleManifest(
            name: $name,
            version: '1.0.0',
            bindings: $moduleConfig['bindings'] ?? [],
            singletons: $moduleConfig['singletons'] ?? [],
            source: 'vendor',
            boot: $moduleConfig['boot'] ?? null,
        );
    }

    public static function config(
        string $host = 'localhost',
        int $port = 5432,
        string $database = 'marko_test',
        string $username = 'marko',
        string $password = 'secret',
    ): DatabaseConfig {
        return DatabaseConfig::fromArray([
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ]);
    }
}
