<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Marko\Inertia\React\Contracts\InertiaReactPublisherInterface;
use Marko\Inertia\React\InertiaReactPublisher;
use Marko\Inertia\React\InertiaReactViteConfigUpdater;

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

it('has a valid composer.json for marko/inertia-react', function (): void {
    $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['name'])->toBe('marko/inertia-react')
        ->and($composer['type'])->toBe('marko-module')
        ->and($composer['license'])->toBe('MIT')
        ->and($composer['require']['php'])->toBe('^8.5')
        ->and($composer['require']['marko/core'])->toBe('self.version')
        ->and($composer['require']['marko/inertia'])->toBe('self.version')
        ->and($composer['require']['marko/vite'])->toBe('self.version')
        ->and($composer['autoload']['psr-4']['Marko\\Inertia\\React\\'])->toBe('src/')
        ->and($composer['autoload-dev']['psr-4']['Marko\\Inertia\\React\\Tests\\'])->toBe('tests/');
});

it('has module.php with bindings for the inertia react services', function (): void {
    $module = require dirname(__DIR__) . '/module.php';

    expect($module)->toBeArray()
        ->and($module)->toHaveKey('bindings')
        ->and($module['bindings'])->toHaveKey(InertiaReactPublisherInterface::class)
        ->and($module['bindings'][InertiaReactPublisherInterface::class])->toBe(InertiaReactPublisher::class)
        ->and($module['singletons'])->toContain(InertiaReactPublisher::class)
        ->and($module['singletons'])->toContain(InertiaReactViteConfigUpdater::class);
});

it('publishes a bootstrap helper with layout resolution and inertia config passthrough support', function (): void {
    $bootstrap = (string) file_get_contents(dirname(__DIR__) . '/resources/js/bootstrap.ts');

    expect($bootstrap)->toContain('defaultLayout?:')
        ->and($bootstrap)->toContain('export function discoverMarkoServerLayouts(')
        ->and($bootstrap)->toContain('resolveLayout?:')
        ->and($bootstrap)->toContain('serverLayouts?:')
        ->and($bootstrap)->toContain('resolveServerLayout?:')
        ->and($bootstrap)->toContain('layout: (component, page) => {')
        ->and($bootstrap)->toContain('page.props?._marko?.layout')
        ->and($bootstrap)->toContain('options.resolveLayout?.({')
        ->and($bootstrap)->toContain('inertia?:')
        ->and($bootstrap)->toContain('typeof options.inertia === "function"')
        ->and($bootstrap)->toContain('return createInertiaApp(inertiaConfig as never);');
});
