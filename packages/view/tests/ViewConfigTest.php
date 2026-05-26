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
        'view.auto_refresh' => true,
    ], $overrides));
}

it('ViewConfig has cache directory property', function (): void {
    $config = createDefaultViewConfigRepository([
        'view.cache_directory' => '/var/cache/views',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/var/cache/views');
});

it('ViewConfig has extension property', function (): void {
    $config = createDefaultViewConfigRepository([
        'view.extension' => '.blade.php',
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->extension())->toBe('.blade.php');
});

it('ViewConfig has auto refresh property', function (): void {
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

it('ViewConfig loads all properties from config repository', function (): void {
    $config = new FakeConfigRepository([
        'view.cache_directory' => '/custom/cache',
        'view.extension' => '.twig',
        'view.auto_refresh' => false,
    ]);

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/custom/cache')
        ->and($viewConfig->extension())->toBe('.twig')
        ->and($viewConfig->autoRefresh())->toBeFalse();
});

it('ViewConfig uses default config values', function (): void {
    $config = createDefaultViewConfigRepository();

    $viewConfig = new ViewConfig($config);

    expect($viewConfig->cacheDirectory())->toBe('/tmp/views')
        ->and($viewConfig->autoRefresh())->toBeTrue();
});

it('ViewConfig throws exception when config key is missing', function (): void {
    $config = new FakeConfigRepository([]);

    $viewConfig = new ViewConfig($config);

    $viewConfig->cacheDirectory();
})->throws(ConfigNotFoundException::class);

it('does not include an extension default in the shipped view config', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('extension', $config))->toBeFalse();
});

it('does not include a strict_types default in the shipped view config', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('strict_types', $config))->toBeFalse();
});

it('keeps cache_directory as a shipped default', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('cache_directory', $config))->toBeTrue();
});

it('keeps auto_refresh as a shipped default', function (): void {
    $config = require dirname(__DIR__) . '/config/view.php';

    expect(array_key_exists('auto_refresh', $config))->toBeTrue();
});

it('ViewConfig::extension() throws ConfigNotFoundException when no driver has set view.extension', function (): void {
    $config = new FakeConfigRepository([]);
    $viewConfig = new ViewConfig($config);

    $viewConfig->extension();
})->throws(ConfigNotFoundException::class);

it('ViewConfig::extension() returns the value when a driver config sets view.extension', function (): void {
    $config = new FakeConfigRepository(['view.extension' => '.twig']);
    $viewConfig = new ViewConfig($config);

    expect($viewConfig->extension())->toBe('.twig');
});

it('ViewConfig no longer exposes a strictTypes() accessor', function (): void {
    expect(method_exists(ViewConfig::class, 'strictTypes'))->toBeFalse();
});

it('uses FakeConfigRepository in ViewConfigTest', function (): void {
    $repo = new FakeConfigRepository([
        'view.cache_directory' => '/tmp/views',
        'view.auto_refresh' => true,
    ]);
    $config = new ViewConfig($repo);

    expect($config->cacheDirectory())->toBe('/tmp/views');
});
