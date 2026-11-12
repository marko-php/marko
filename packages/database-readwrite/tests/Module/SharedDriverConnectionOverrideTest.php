<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Tests\Module;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\ReadWrite\Connection\ReadWriteConnection;

readonly class SharedOverrideConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private string $driver,
    ) {}

    public function get(
        string $key,
        ?string $scope = null,
    ): mixed {
        $connection = [
            'driver' => $this->driver,
            'host' => 'db',
            'port' => 5432,
            'database' => 'app',
            'username' => 'app',
            'password' => 'secret',
        ];

        return match ($key) {
            'database.driver' => 'readwrite',
            'database.connections' => [
                'write' => $connection,
                'read' => [[...$connection, 'host' => 'replica']],
            ],
            default => null,
        };
    }

    public function has(
        string $key,
        ?string $scope = null,
    ): bool {
        return $this->get($key) !== null;
    }

    public function getString(
        string $key,
        ?string $scope = null,
    ): string {
        return (string) $this->get($key);
    }

    public function getInt(
        string $key,
        ?string $scope = null,
    ): int {
        return (int) $this->get($key);
    }

    public function getBool(
        string $key,
        ?string $scope = null,
    ): bool {
        return (bool) $this->get($key);
    }

    public function getFloat(
        string $key,
        ?string $scope = null,
    ): float {
        return (float) $this->get($key);
    }

    public function getArray(
        string $key,
        ?string $scope = null,
    ): array {
        return (array) $this->get($key);
    }

    public function all(
        ?string $scope = null,
    ): array {
        return [];
    }

    public function withScope(
        string $scope,
    ): ConfigRepositoryInterface {
        return $this;
    }
}

/**
 * Registers the real marko/database, driver and marko/database-readwrite
 * manifests, then runs boot callbacks, mirroring Application::initialize().
 */
function buildReadWriteOverContainer(string $driver): Container
{
    $packagesPath = dirname(__DIR__, 3);
    $modules = [];

    foreach (['database', "database-$driver", 'database-readwrite'] as $package) {
        $moduleConfig = require "$packagesPath/$package/module.php";
        $modules[] = new ModuleManifest(
            name: "marko/$package",
            version: '1.0.0',
            bindings: $moduleConfig['bindings'] ?? [],
            singletons: $moduleConfig['singletons'] ?? [],
            arguments: $moduleConfig['arguments'] ?? [],
            source: 'vendor',
            // marko/database's boot only scans for entities; it needs ProjectPaths and is irrelevant here.
            boot: $package === 'database' ? null : ($moduleConfig['boot'] ?? null),
        );
    }

    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ConfigRepositoryInterface::class, new SharedOverrideConfigRepository($driver));
    // marko/core binds the default sleeper in a real application.
    $container->bind(SleeperInterface::class, UsleepSleeper::class);

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

describe('database-readwrite over a shared driver connection', function (): void {
    it('resolves ConnectionInterface to the ReadWriteConnection over the shared pgsql binding', function (): void {
        $container = buildReadWriteOverContainer('pgsql');

        expect($container->get(ConnectionInterface::class))->toBeInstanceOf(ReadWriteConnection::class)
            ->and($container->get(ConnectionInterface::class))->toBe($container->get(ConnectionInterface::class));
    });

    it('resolves TransactionInterface to the same ReadWriteConnection', function (): void {
        $container = buildReadWriteOverContainer('pgsql');

        expect($container->get(TransactionInterface::class))->toBeInstanceOf(ReadWriteConnection::class)
            ->and($container->get(TransactionInterface::class))->toBe($container->get(ConnectionInterface::class));
    });

    it('resolves ConnectionInterface to the ReadWriteConnection over the shared mysql binding', function (): void {
        $container = buildReadWriteOverContainer('mysql');

        expect($container->get(ConnectionInterface::class))->toBeInstanceOf(ReadWriteConnection::class)
            ->and($container->get(TransactionInterface::class))->toBe($container->get(ConnectionInterface::class));
    });
});
