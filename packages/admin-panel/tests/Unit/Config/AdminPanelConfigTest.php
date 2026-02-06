<?php

declare(strict_types=1);

use Marko\AdminPanel\Config\AdminPanelConfig;
use Marko\AdminPanel\Config\AdminPanelConfigInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

function createAdminPanelMockConfigRepository(
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

it('creates AdminPanelConfig with pageTitle and itemsPerPage settings', function (): void {
    $config = new AdminPanelConfig(createAdminPanelMockConfigRepository([
        'admin-panel.page_title' => 'My Custom Admin',
        'admin-panel.items_per_page' => 50,
    ]));

    expect($config)->toBeInstanceOf(AdminPanelConfigInterface::class)
        ->and($config->getPageTitle())->toBe('My Custom Admin')
        ->and($config->getItemsPerPage())->toBe(50);
});

it('provides default page title of Marko Admin', function (): void {
    $config = new AdminPanelConfig(createAdminPanelMockConfigRepository([
        'admin-panel.page_title' => 'Marko Admin',
        'admin-panel.items_per_page' => 20,
    ]));

    expect($config->getPageTitle())->toBe('Marko Admin');
});

it('provides default items per page of 20', function (): void {
    $config = new AdminPanelConfig(createAdminPanelMockConfigRepository([
        'admin-panel.page_title' => 'Marko Admin',
        'admin-panel.items_per_page' => 20,
    ]));

    expect($config->getItemsPerPage())->toBe(20);
});

it('has valid config/admin-panel.php with default values', function (): void {
    $configPath = dirname(__DIR__, 3) . '/config/admin-panel.php';

    expect(file_exists($configPath))->toBeTrue();

    $configData = require $configPath;

    expect($configData)->toBeArray()
        ->and($configData)->toHaveKey('page_title')
        ->and($configData)->toHaveKey('items_per_page')
        ->and($configData['page_title'])->toBe('Marko Admin')
        ->and($configData['items_per_page'])->toBe(20);
});

it('binds AdminPanelConfigInterface to AdminPanelConfig in module.php', function (): void {
    $modulePath = dirname(__DIR__, 3) . '/module.php';

    expect(file_exists($modulePath))->toBeTrue();

    $module = require $modulePath;

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(AdminPanelConfigInterface::class)
        ->and($module['bindings'][AdminPanelConfigInterface::class])
            ->toBe(AdminPanelConfig::class);
});
