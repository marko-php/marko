<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\View\ViewConfig;

function createViewConfigRepository(
    array $values = [],
): ConfigRepositoryInterface {
    return new readonly class ($values) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $values,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->values[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->values);
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
    $config = createViewConfigRepository([
        'view.cache_directory' => '/var/cache/views',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/var/cache/views');
});

it('ViewConfig has extension with default', function () {
    // Test with explicit value
    $configWithExtension = createViewConfigRepository([
        'view.extension' => '.blade.php',
    ]);
    $viewConfigWithExtension = new ViewConfig($configWithExtension);
    expect($viewConfigWithExtension->extension())->toBe('.blade.php');

    // Test default value
    $configEmpty = createViewConfigRepository();
    $viewConfigEmpty = new ViewConfig($configEmpty);
    expect($viewConfigEmpty->extension())->toBe('.latte');
});

it('ViewConfig has auto refresh with default', function () {
    // Test with explicit value (true)
    $configWithAutoRefresh = createViewConfigRepository([
        'view.auto_refresh' => true,
    ]);
    $viewConfigWithAutoRefresh = new ViewConfig($configWithAutoRefresh);
    expect($viewConfigWithAutoRefresh->autoRefresh())->toBeTrue();

    // Test default value (true for dev)
    $configEmpty = createViewConfigRepository();
    $viewConfigEmpty = new ViewConfig($configEmpty);
    expect($viewConfigEmpty->autoRefresh())->toBeTrue();

    // Test with explicit false
    $configWithAutoRefreshFalse = createViewConfigRepository([
        'view.auto_refresh' => false,
    ]);
    $viewConfigWithAutoRefreshFalse = new ViewConfig($configWithAutoRefreshFalse);
    expect($viewConfigWithAutoRefreshFalse->autoRefresh())->toBeFalse();
});

it('ViewConfig loads from config repository', function () {
    // Test that all properties are loaded from the config repository
    $config = createViewConfigRepository([
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
    $emptyConfig = createViewConfigRepository();
    $emptyViewConfig = new ViewConfig($emptyConfig);

    expect($emptyViewConfig->cacheDirectory())->toBe('/tmp/views')
        ->and($emptyViewConfig->extension())->toBe('.latte')
        ->and($emptyViewConfig->autoRefresh())->toBeTrue()
        ->and($emptyViewConfig->strictTypes())->toBeTrue();
});
