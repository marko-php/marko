<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RoutingConfig;
use Marko\Routing\UrlGenerator;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\Functions\RouteFunctionProvider;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

function routeFunctionProvider(): RouteFunctionProvider
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/shows/{id:\d+}',
        controller: 'C',
        action: 'show',
        name: 'shows.show',
    ));

    return new RouteFunctionProvider(new UrlGenerator(
        $routes,
        new RoutingConfig(new FakeConfigRepository(['routing.url' => 'https://example.com'])),
    ));
}

describe('RouteFunctionProvider', function (): void {
    it('is a discoverable template function provider', function (): void {
        $reflection = new ReflectionClass(RouteFunctionProvider::class);

        expect($reflection->implementsInterface(TemplateFunctionProviderInterface::class))->toBeTrue()
            ->and($reflection->getAttributes(TemplateFunctions::class))->toHaveCount(1);
    });

    it('provides one escaped route function', function (): void {
        $functions = routeFunctionProvider()->functions();

        expect($functions)->toHaveCount(1)
            ->and($functions[0])->toBeInstanceOf(TemplateFunction::class)
            ->and($functions[0]->name)->toBe('route')
            ->and($functions[0]->isSafeHtml)->toBeFalse();
    });

    it('builds URLs through the URL generator', function (): void {
        $route = routeFunctionProvider()->functions()[0]->callable;

        expect($route('shows.show', ['id' => 5]))->toBe('/shows/5')
            ->and($route('shows.show', ['id' => 5, 'tab' => 'cast'], absolute: true))
            ->toBe('https://example.com/shows/5?tab=cast');
    });

    it('lets a URL generation exception propagate', function (): void {
        $route = routeFunctionProvider()->functions()[0]->callable;

        $route('shows.missing');
    })->throws(UrlGenerationException::class, "No route named 'shows.missing'");
});
