<?php

declare(strict_types=1);

use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\View\Discovery\DiscoveredViewProviders;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Discovery\ViewProviderDiscovery;
use Marko\View\Functions\RouteFunctionProvider;
use Marko\View\Tests\Fixtures\GreetingFunctionProvider;

function discoveredViewProviders(
    CachedDiscovery $cachedDiscovery,
    ModuleManifest ...$modules,
): DiscoveredViewProviders {
    $discovery = new ViewProviderDiscovery();

    return new DiscoveredViewProviders(
        cachedDiscovery: $cachedDiscovery,
        viewProviderCacheContributor: new ViewProviderCacheContributor($discovery),
        viewProviderDiscovery: $discovery,
        moduleRepository: new ModuleRepository($modules),
    );
}

describe('DiscoveredViewProviders', function (): void {
    it('reads the provider classes from the cache when the boot used it', function (): void {
        // marko/view is enabled, but a cached boot must not scan it.
        $providers = discoveredViewProviders(
            new CachedDiscovery([
                ViewProviderCacheContributor::KEY => [
                    'template_functions' => [GreetingFunctionProvider::class],
                    'view_data' => [],
                ],
            ]),
            new ModuleManifest(name: 'marko/view', version: '1.0.0', path: dirname(__DIR__, 2)),
        );

        expect($providers->classes()->templateFunctionProviders)->toBe([GreetingFunctionProvider::class]);
    });

    it('scans the enabled modules on a live boot', function (): void {
        $providers = discoveredViewProviders(
            new CachedDiscovery(),
            new ModuleManifest(name: 'marko/view', version: '1.0.0', path: dirname(__DIR__, 2)),
        );

        expect($providers->classes()->templateFunctionProviders)->toBe([RouteFunctionProvider::class]);
    });

    it('discovers once per process', function (): void {
        $providers = discoveredViewProviders(new CachedDiscovery());

        expect($providers->classes())->toBe($providers->classes());
    });
});
