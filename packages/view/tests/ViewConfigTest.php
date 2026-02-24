<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\ViewConfig;

/**
 * Helper to create a config repository with all default view config values.
 */
function createDefaultViewConfigRepository(
    array $overrides = [],
): FakeConfigRepository {
    return new FakeConfigRepository(array_merge([
        'view.cache_directory' => '/tmp/views',
        'view.extension' => '.latte',
        'view.auto_refresh' => true,
        'view.strict_types' => true,
    ], $overrides));
}

it('ViewConfig has cache directory property', function () {
    $config = createDefaultViewConfigRepository([
        'view.cache_directory' => '/var/cache/views',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/var/cache/views');
});

it('ViewConfig has extension property', function () {
    $config = createDefaultViewConfigRepository([
        'view.extension' => '.blade.php',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->extension())->toBe('.blade.php');
});

it('ViewConfig has auto refresh property', function () {
    // Test with explicit true
    $configTrue = createDefaultViewConfigRepository([
        'view.auto_refresh' => true,
    ]);
    $viewConfigTrue = new ViewConfig($configTrue);
    expect($viewConfigTrue->autoRefresh())->toBeTrue();

    // Test with explicit false
    $configFalse = createDefaultViewConfigRepository([
        'view.auto_refresh' => false,
    ]);
    $viewConfigFalse = new ViewConfig($configFalse);
    expect($viewConfigFalse->autoRefresh())->toBeFalse();
});

it('ViewConfig has strict types property', function () {
    // Test with explicit true
    $configTrue = createDefaultViewConfigRepository([
        'view.strict_types' => true,
    ]);
    $viewConfigTrue = new ViewConfig($configTrue);
    expect($viewConfigTrue->strictTypes())->toBeTrue();

    // Test with explicit false
    $configFalse = createDefaultViewConfigRepository([
        'view.strict_types' => false,
    ]);
    $viewConfigFalse = new ViewConfig($configFalse);
    expect($viewConfigFalse->strictTypes())->toBeFalse();
});

it('ViewConfig loads all properties from config repository', function () {
    $config = new FakeConfigRepository([
        'view.cache_directory' => '/custom/cache',
        'view.extension' => '.twig',
        'view.auto_refresh' => false,
        'view.strict_types' => false,
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/custom/cache')
        ->and($viewConfig->extension())->toBe('.twig')
        ->and($viewConfig->autoRefresh())->toBeFalse()
        ->and($viewConfig->strictTypes())->toBeFalse();
});

it('ViewConfig uses default config values', function () {
    $config = createDefaultViewConfigRepository();

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/tmp/views')
        ->and($viewConfig->extension())->toBe('.latte')
        ->and($viewConfig->autoRefresh())->toBeTrue()
        ->and($viewConfig->strictTypes())->toBeTrue();
});

it('ViewConfig throws exception when config key is missing', function () {
    $config = new FakeConfigRepository([]);

    $viewConfig = new ViewConfig($config);

    $viewConfig->cacheDirectory();
})->throws(ConfigNotFoundException::class);

it('uses FakeConfigRepository in ViewConfigTest', function () {
    $repo = new FakeConfigRepository(['view.cache_directory' => '/tmp/views', 'view.extension' => '.latte', 'view.auto_refresh' => true, 'view.strict_types' => true]);
    $config = new ViewConfig($repo);

    expect($config->cacheDirectory())->toBe('/tmp/views');
});
