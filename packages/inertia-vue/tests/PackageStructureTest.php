<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Marko\Inertia\Vue\Contracts\InertiaVuePublisherInterface;
use Marko\Inertia\Vue\InertiaVuePublisher;
use Marko\Inertia\Vue\InertiaVueViteConfigUpdater;

it(
    'creates valid package scaffolding with composer.json, module.php, src, tests, resources, stubs, and docs metadata',
    function (): void {
        $packageRoot = dirname(__DIR__);
    
        expect(file_exists($packageRoot . '/composer.json'))->toBeTrue()
            ->and(file_exists($packageRoot . '/README.md'))->toBeTrue()
            ->and(file_exists($packageRoot . '/module.php'))->toBeTrue()
            ->and(file_exists($packageRoot . '/.gitattributes'))->toBeTrue()
            ->and(is_dir($packageRoot . '/src'))->toBeTrue()
            ->and(is_dir($packageRoot . '/tests'))->toBeTrue()
            ->and(is_dir($packageRoot . '/resources'))->toBeTrue()
            ->and(is_dir($packageRoot . '/stubs'))->toBeTrue()
            ->and(file_exists($packageRoot . '/resources/js/bootstrap.ts'))->toBeTrue()
            ->and(file_exists($packageRoot . '/stubs/resources/js/app.ts'))->toBeTrue();
    }
);

it('has a valid composer.json for marko/inertia-vue', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('marko/inertia-vue')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require']['marko/inertia'])->toBe('self.version')
        ->and($composer['require']['marko/vite'])->toBe('self.version')
        ->and($composer['autoload']['psr-4']['Marko\\Inertia\\Vue\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Inertia\\Vue\\Tests\\'])->toBe('tests/');
});

it('has module.php with bindings for the inertia vue services', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(InertiaVuePublisherInterface::class)
        ->and($module['bindings'][InertiaVuePublisherInterface::class])->toBe(InertiaVuePublisher::class)
        ->and($module['singletons'])->toContain(InertiaVuePublisher::class)
        ->and($module['singletons'])->toContain(InertiaVueViteConfigUpdater::class);
});

it('publishes a bootstrap helper with layout resolution and inertia config passthrough support', function (): void {
    $bootstrap = (string) file_get_contents(dirname(__DIR__) . '/resources/js/bootstrap.ts');

    expect($bootstrap)->toContain('defaultLayout?:')
        ->and($bootstrap)->toContain('export function discoverMarkoServerLayouts(')
        ->and($bootstrap)->toContain('resolveLayout?:')
        ->and($bootstrap)->toContain('serverLayouts?:')
        ->and($bootstrap)->toContain('resolveServerLayout?:')
        ->and($bootstrap)->toContain('resolve: async (component, page) => {')
        ->and($bootstrap)->toContain('page.props?._marko?.layout')
        ->and($bootstrap)->toContain('applyResolvedLayout(')
        ->and($bootstrap)->toContain('options.resolveLayout?.({')
        ->and($bootstrap)->toContain('inertia?:')
        ->and($bootstrap)->toContain('typeof options.inertia === "function"')
        ->and($bootstrap)->toContain('return createInertiaApp(inertiaConfig as never);');
});
