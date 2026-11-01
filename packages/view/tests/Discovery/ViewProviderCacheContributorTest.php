<?php

declare(strict_types=1);

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Discovery\ViewProviderDiscovery;
use Marko\View\Functions\RouteFunctionProvider;
use Marko\View\Functions\SignedRouteFunctionProvider;
use Marko\View\Tests\Fixtures\GreetingFunctionProvider;
use Marko\View\Tests\Fixtures\SiteDataProvider;

describe('ViewProviderCacheContributor', function (): void {
    it('is a discovery cache contributor with the view_providers key', function (): void {
        $contributor = new ViewProviderCacheContributor(new ViewProviderDiscovery());

        expect($contributor)->toBeInstanceOf(DiscoveryCacheContributorInterface::class)
            ->and($contributor->key())->toBe('view_providers');
    });

    it('compiles the discovered provider class names', function (): void {
        $contributor = new ViewProviderCacheContributor(new ViewProviderDiscovery());
        $module = new ModuleManifest(name: 'marko/view', version: '1.0.0', path: dirname(__DIR__, 2));

        $compiled = $contributor->compile([$module]);

        expect(array_keys($compiled))->toBe(['template_functions', 'view_data'])
            ->and($compiled['template_functions'])
            ->toEqualCanonicalizing([RouteFunctionProvider::class, SignedRouteFunctionProvider::class])
            ->and($compiled['view_data'])->toBe([]);
    });

    it('hydrates the provider class names from a cached section', function (): void {
        $contributor = new ViewProviderCacheContributor(new ViewProviderDiscovery());

        $classes = $contributor->hydrate([
            'template_functions' => [GreetingFunctionProvider::class],
            'view_data' => [SiteDataProvider::class],
        ]);

        expect($classes->templateFunctionProviders)->toBe([GreetingFunctionProvider::class])
            ->and($classes->viewDataProviders)->toBe([SiteDataProvider::class]);
    });

    it('throws on a malformed cached section', function (array $section): void {
        new ViewProviderCacheContributor(new ViewProviderDiscovery())->hydrate($section);
    })->throws(DiscoveryCacheException::class)->with([
        'missing view_data' => [['template_functions' => []]],
        'non-list template_functions' => [['template_functions' => 'x', 'view_data' => []]],
        'non-string class name' => [['template_functions' => [1], 'view_data' => []]],
    ]);
});
