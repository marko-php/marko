<?php

declare(strict_types=1);

use Marko\Inertia\InertiaConfig;
use Marko\Testing\Fake\FakeConfigRepository;

function inertiaConfigRepository(array $overrides = []): FakeConfigRepository
{
    return new FakeConfigRepository(array_replace_recursive([
        'app.env' => 'testing',
        'inertia.version' => 'test-version',
        'inertia.root.id' => 'app',
        'inertia.root.title' => 'Marko',
        'inertia.page.ensure_pages_exist' => false,
        'inertia.page.paths' => ['resources/js/pages'],
        'inertia.page.extensions' => ['js', 'ts', 'tsx'],
        'inertia.testing.ensure_pages_exist' => true,
        'inertia.history.encrypt' => false,
    ], $overrides));
}

it('returns normalized page paths and extensions', function (): void {
    $config = new InertiaConfig(inertiaConfigRepository([
        'inertia.page.paths' => [' resources/js/pages ', '/resources/js/admin/pages/'],
        'inertia.page.extensions' => ['.JS', ' tsx ', ''],
    ]));

    expect($config->pagePaths())->toBe([
        'resources/js/pages',
        'resources/js/admin/pages',
    ])->and($config->pageExtensions())->toBe([
        'js',
        'tsx',
    ]);
});

it('supports the legacy config keys during the refactor', function (): void {
    $config = new InertiaConfig(new FakeConfigRepository([
        'inertia.version' => 'legacy-version',
        'inertia.root_view.id' => 'legacy-root',
        'inertia.root_view.title' => 'Legacy Marko',
        'inertia.pages.ensure_pages_exist' => true,
        'inertia.pages.paths' => ['resources/js/LegacyPages'],
        'inertia.pages.extensions' => ['vue'],
        'inertia.testing.ensure_pages_exist' => false,
        'inertia.history.encrypt' => true,
    ]));

    expect($config->version())->toBe('legacy-version')
        ->and($config->rootElementId())->toBe('legacy-root')
        ->and($config->rootTitle())->toBe('Legacy Marko')
        ->and($config->pagePaths())->toBe(['resources/js/LegacyPages'])
        ->and($config->pageExtensions())->toBe(['vue'])
        ->and($config->encryptHistory())->toBeTrue();
});

it('uses testing ensure pages setting while running tests', function (): void {
    $config = new InertiaConfig(inertiaConfigRepository([
        'inertia.page.ensure_pages_exist' => false,
        'inertia.testing.ensure_pages_exist' => true,
    ]));

    expect($config->shouldEnsurePagesExist())->toBeTrue();
});

it('exposes root settings version and history encryption', function (): void {
    $config = new InertiaConfig(inertiaConfigRepository([
        'inertia.version' => 'abc123',
        'inertia.root.id' => 'frontend',
        'inertia.root.title' => 'Admin',
        'inertia.history.encrypt' => true,
    ]));

    expect($config->version())->toBe('abc123')
        ->and($config->rootElementId())->toBe('frontend')
        ->and($config->rootTitle())->toBe('Admin')
        ->and($config->encryptHistory())->toBeTrue();
});
