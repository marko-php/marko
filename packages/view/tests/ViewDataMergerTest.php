<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\Tests\Fixtures\ConflictingDataProvider;
use Marko\View\Tests\Fixtures\SiteDataProvider;
use Marko\View\ViewDataMerger;

/**
 * @param list<class-string> $dataProviders
 */
function viewDataMergerContainer(
    array $dataProviders,
): Container {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));
    $container->instance(CachedDiscovery::class, new CachedDiscovery([
        ViewProviderCacheContributor::KEY => [
            'template_functions' => [],
            'view_data' => $dataProviders,
        ],
    ]));

    return $container;
}

describe('ViewDataMerger', function (): void {
    it('adds every provider value to the render data', function (): void {
        $merger = viewDataMergerContainer([SiteDataProvider::class])->get(ViewDataMerger::class);

        expect($merger->merge(['title' => 'Home']))->toBe([
            'siteName' => 'Marko',
            'tagline' => 'Modular PHP',
            'title' => 'Home',
        ]);
    });

    it('lets data passed to render win over provider data', function (): void {
        $merger = viewDataMergerContainer([SiteDataProvider::class])->get(ViewDataMerger::class);

        expect($merger->merge(['siteName' => 'Explicit']))->toMatchArray([
            'siteName' => 'Explicit',
            'tagline' => 'Modular PHP',
        ]);
    });

    it('returns the render data unchanged when no provider is discovered', function (): void {
        $merger = viewDataMergerContainer([])->get(ViewDataMerger::class);

        expect($merger->merge(['title' => 'Home']))->toBe(['title' => 'Home']);
    });

    it('asks providers for their data on every render', function (): void {
        $provider = new SiteDataProvider();
        $container = viewDataMergerContainer([SiteDataProvider::class]);
        $container->instance(SiteDataProvider::class, $provider);
        $merger = $container->get(ViewDataMerger::class);

        $merger->merge([]);
        $merger->merge([]);

        expect($provider->calls)->toBe(2);
    });

    it('throws naming both providers when two supply the same key', function (): void {
        viewDataMergerContainer([SiteDataProvider::class, ConflictingDataProvider::class])
            ->get(ViewDataMerger::class)
            ->merge([]);
    })->throws(
        ViewProviderException::class,
        "View data key 'siteName' is supplied by both " . SiteDataProvider::class
            . ' and ' . ConflictingDataProvider::class,
    );
});
