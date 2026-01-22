<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\View\ViewConfig;

function createMockConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            mixed $default = null,
            ?string $scope = null,
        ): mixed {
            return $this->values[$key] ?? $default;
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->values);
        }

        public function getString(
            string $key,
            ?string $default = null,
            ?string $scope = null,
        ): string {
            return (string) ($this->values[$key] ?? $default);
        }

        public function getInt(
            string $key,
            ?int $default = null,
            ?string $scope = null,
        ): int {
            return (int) ($this->values[$key] ?? $default);
        }

        public function getBool(
            string $key,
            ?bool $default = null,
            ?string $scope = null,
        ): bool {
            return (bool) ($this->values[$key] ?? $default);
        }

        public function getFloat(
            string $key,
            ?float $default = null,
            ?string $scope = null,
        ): float {
            return (float) ($this->values[$key] ?? $default);
        }

        public function getArray(
            string $key,
            ?array $default = null,
            ?string $scope = null,
        ): array {
            return (array) ($this->values[$key] ?? $default);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->values;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };
}

it('ViewConfig has cache directory property', function () {
    $config = createMockConfigRepository([
        'view.cache_directory' => '/var/cache/views',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/var/cache/views');
});

it('ViewConfig has extension with default', function () {
    // Test with explicit value
    $configWithExtension = createMockConfigRepository([
        'view.extension' => '.blade.php',
    ]);
    $viewConfigWithExtension = new ViewConfig($configWithExtension);
    expect($viewConfigWithExtension->extension())->toBe('.blade.php');

    // Test default value
    $configEmpty = createMockConfigRepository([]);
    $viewConfigEmpty = new ViewConfig($configEmpty);
    expect($viewConfigEmpty->extension())->toBe('.latte');
});

it('ViewConfig has auto refresh with default', function () {
    // Test with explicit value (true)
    $configWithAutoRefresh = createMockConfigRepository([
        'view.auto_refresh' => true,
    ]);
    $viewConfigWithAutoRefresh = new ViewConfig($configWithAutoRefresh);
    expect($viewConfigWithAutoRefresh->autoRefresh())->toBeTrue();

    // Test default value (true for dev)
    $configEmpty = createMockConfigRepository([]);
    $viewConfigEmpty = new ViewConfig($configEmpty);
    expect($viewConfigEmpty->autoRefresh())->toBeTrue();

    // Test with explicit false
    $configWithAutoRefreshFalse = createMockConfigRepository([
        'view.auto_refresh' => false,
    ]);
    $viewConfigWithAutoRefreshFalse = new ViewConfig($configWithAutoRefreshFalse);
    expect($viewConfigWithAutoRefreshFalse->autoRefresh())->toBeFalse();
});

it('ViewConfig loads from config repository', function () {
    // Test that all properties are loaded from the config repository
    $config = createMockConfigRepository([
        'view.cache_directory' => '/custom/cache',
        'view.extension' => '.twig',
        'view.auto_refresh' => false,
        'view.strict_types' => true,
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/custom/cache')
        ->and($viewConfig->extension())->toBe('.twig')
        ->and($viewConfig->autoRefresh())->toBeFalse()
        ->and($viewConfig->strictTypes())->toBeTrue();

    // Test default values when config is empty
    $emptyConfig = createMockConfigRepository([]);
    $emptyViewConfig = new ViewConfig($emptyConfig);

    expect($emptyViewConfig->cacheDirectory())->toBe('/tmp/views')
        ->and($emptyViewConfig->extension())->toBe('.latte')
        ->and($emptyViewConfig->autoRefresh())->toBeTrue()
        ->and($emptyViewConfig->strictTypes())->toBeTrue();
});
