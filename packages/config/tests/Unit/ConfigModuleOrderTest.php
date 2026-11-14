<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigModuleOrder;
use Marko\Core\Module\ModuleManifest;

function orderedModule(
    string $name,
    string $source,
): ModuleManifest {
    return new ModuleManifest(name: $name, version: '1.0.0', path: '/' . $name, source: $source);
}

it('orders app module config after vendor module config even when the vendor module loads later', function (): void {
    $paths = (new ConfigModuleOrder())->paths([
        orderedModule('marko/core', 'vendor'),
        orderedModule('app/my-app', 'app'),
        orderedModule('marko/cache', 'vendor'),
    ]);

    expect($paths)->toBe(['/marko/core', '/marko/cache', '/app/my-app']);
});

it('merges an app module config value over a vendor module value for the same key', function (): void {
    $fixtures = __DIR__ . '/fixtures/order';
    $paths = (new ConfigModuleOrder())->paths([
        new ModuleManifest(name: 'app/my-app', version: '1.0.0', path: $fixtures . '/app-module', source: 'app'),
        new ModuleManifest(
            name: 'marko/cache',
            version: '1.0.0',
            path: $fixtures . '/vendor-module',
            source: 'vendor',
        ),
    ]);

    $result = (new ConfigDiscovery(new ConfigLoader(), new ConfigMerger()))
        ->discover($paths, $fixtures . '/no-root');

    expect($result)->toBe(['cache' => ['driver' => 'app', 'ttl' => 60]]);
});

it('lets the project root config override app module config', function (): void {
    $fixtures = __DIR__ . '/fixtures/order';
    $paths = (new ConfigModuleOrder())->paths([
        new ModuleManifest(name: 'app/my-app', version: '1.0.0', path: $fixtures . '/app-module', source: 'app'),
        new ModuleManifest(
            name: 'marko/cache',
            version: '1.0.0',
            path: $fixtures . '/vendor-module',
            source: 'vendor',
        ),
    ]);

    $result = (new ConfigDiscovery(new ConfigLoader(), new ConfigMerger()))
        ->discover($paths, $fixtures . '/root');

    expect($result)->toBe(['cache' => ['driver' => 'root', 'ttl' => 60]]);
});

it('keeps dependency load order between modules from the same source', function (): void {
    $paths = (new ConfigModuleOrder())->paths([
        orderedModule('app/b', 'app'),
        orderedModule('app/a', 'app'),
        orderedModule('app/c', 'app'),
    ]);

    expect($paths)->toBe(['/app/b', '/app/a', '/app/c']);
});

it('treats a module with an unknown source as vendor priority', function (): void {
    $paths = (new ConfigModuleOrder())->paths([
        orderedModule('marko/core', 'vendor'),
        orderedModule('app/my-app', 'app'),
        orderedModule('acme/loose', ''),
    ]);

    expect($paths)->toBe(['/marko/core', '/acme/loose', '/app/my-app']);
});

it('orders modules directory config between vendor and app module config', function (): void {
    $paths = (new ConfigModuleOrder())->paths([
        orderedModule('app/my-app', 'app'),
        orderedModule('acme/shop', 'modules'),
        orderedModule('marko/core', 'vendor'),
    ]);

    expect($paths)->toBe(['/marko/core', '/acme/shop', '/app/my-app']);
});
