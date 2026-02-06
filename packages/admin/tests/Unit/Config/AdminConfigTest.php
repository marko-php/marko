<?php

declare(strict_types=1);

use Marko\Admin\Config\AdminConfig;
use Marko\Admin\Config\AdminConfigInterface;
use Marko\Admin\Exceptions\InvalidAdminConfigException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

function createAdminMockConfigRepository(
    array $configData = [],
): ConfigRepositoryInterface {
    return new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return isset($this->data[$key]);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('provides default admin route prefix of /admin', function (): void {
    $config = new AdminConfig(createAdminMockConfigRepository([
        'admin.route_prefix' => '/admin',
    ]));

    expect($config->getRoutePrefix())->toBe('/admin');
});

it('provides configurable admin route prefix from config', function (): void {
    $config = new AdminConfig(createAdminMockConfigRepository([
        'admin.route_prefix' => '/backend',
    ]));

    expect($config->getRoutePrefix())->toBe('/backend');
});

it('throws InvalidAdminConfigException when route prefix does not start with slash', function (): void {
    $config = new AdminConfig(createAdminMockConfigRepository([
        'admin.route_prefix' => 'admin',
    ]));

    expect(fn () => $config->getRoutePrefix())
        ->toThrow(InvalidAdminConfigException::class, "must start with '/'");
});

it('provides default admin name of Admin', function (): void {
    $config = new AdminConfig(createAdminMockConfigRepository([
        'admin.name' => 'Admin',
    ]));

    expect($config->getName())->toBe('Admin');
});

it('provides configurable admin name from config', function (): void {
    $config = new AdminConfig(createAdminMockConfigRepository([
        'admin.name' => 'Dashboard',
    ]));

    expect($config->getName())->toBe('Dashboard');
});

it('has valid config/admin.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin.php';

    expect(file_exists($configPath))->toBeTrue();

    $configData = require $configPath;

    expect($configData)->toBeArray()
        ->and($configData)->toHaveKey('name')
        ->and($configData)->toHaveKey('route_prefix')
        ->and($configData['name'])->toBe('Admin')
        ->and($configData['route_prefix'])->toBe('/admin');
});

it('binds AdminConfigInterface to AdminConfig in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminConfigInterface::class)
        ->and($module['bindings'][AdminConfigInterface::class])
            ->toBe(AdminConfig::class);
});
