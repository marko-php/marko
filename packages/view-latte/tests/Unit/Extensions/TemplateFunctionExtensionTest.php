<?php

declare(strict_types=1);

use Latte\Engine;
use Latte\Loaders\StringLoader;
use Marko\Core\Path\ProjectPaths;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RoutingConfig;
use Marko\Routing\UrlGenerator;
use Marko\Routing\UrlGeneratorInterface;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Functions\RouteFunctionProvider;
use Marko\View\Latte\Extensions\TemplateFunctionExtension;
use Marko\View\Latte\LatteEngineFactory;
use Marko\View\Latte\LatteViewConfig;
use Marko\View\Latte\Tests\Fixtures\MarkupFunctionProvider;
use Marko\View\Latte\Tests\Fixtures\PathTemplateResolver;
use Marko\View\Latte\Tests\Fixtures\ViewProviders;
use Marko\View\ViewConfig;

function latteFunctionEngine(
    TemplateFunctionExtension $extension,
    string $template,
): Engine {
    $engine = new Engine();
    $engine->setLoader(new StringLoader(['main' => $template]));
    $engine->addExtension($extension);

    return $engine;
}

function latteRouteExtension(): TemplateFunctionExtension
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/shows/{id:\d+}',
        controller: 'C',
        action: 'show',
        name: 'shows.show',
    ));
    $container = ViewProviders::container([RouteFunctionProvider::class]);
    $container->instance(UrlGeneratorInterface::class, new UrlGenerator(
        $routes,
        new RoutingConfig(new FakeConfigRepository(['routing.url' => 'https://example.com'])),
    ));

    return $container->get(TemplateFunctionExtension::class);
}

describe('TemplateFunctionExtension', function (): void {
    it('escapes the output of a function that is not declared safe HTML', function (): void {
        $html = latteFunctionEngine(ViewProviders::extension([MarkupFunctionProvider::class]), '{escaped("x")}')
            ->renderToString('main');

        expect($html)->toBe('&lt;b&gt;x&lt;/b&gt;');
    });

    it('prints the output of a safe HTML function unescaped', function (): void {
        $html = latteFunctionEngine(ViewProviders::extension([MarkupFunctionProvider::class]), '{raw("x")}')
            ->renderToString('main');

        expect($html)->toBe('<b>x</b>');
    });

    it('registers no functions when no provider is discovered', function (): void {
        expect(ViewProviders::extension()->getFunctions())->toBe([]);
    });

    describe('route()', function (): void {
        it('renders a route URL', function (): void {
            $html = latteFunctionEngine(latteRouteExtension(), '{route("shows.show", [id: 5])}')
                ->renderToString('main');

            expect($html)->toBe('/shows/5');
        });

        it('passes parameters and the absolute flag to the URL generator', function (): void {
            $html = latteFunctionEngine(
                latteRouteExtension(),
                '{route("shows.show", [id: 5, tab: "cast"], absolute: true)}',
            )->renderToString('main');

            expect($html)->toBe('https://example.com/shows/5?tab=cast');
        });

        it('escapes the generated URL in an attribute context', function (): void {
            $html = latteFunctionEngine(
                latteRouteExtension(),
                '<a href={route("shows.show", [id: 5, a: 1, b: 2])}>x</a>',
            )->renderToString('main');

            expect($html)->toBe('<a href="/shows/5?a=1&amp;b=2">x</a>');
        });

        it('lets a URL generation exception fail the render', function (): void {
            latteFunctionEngine(latteRouteExtension(), '{route("shows.missing")}')->renderToString('main');
        })->throws(UrlGenerationException::class, "No route named 'shows.missing'");
    });

    it('is registered on the engine by LatteEngineFactory', function (): void {
        $cacheDir = sys_get_temp_dir() . '/latte-functions-' . bin2hex(random_bytes(4));
        mkdir($cacheDir, 0755, true);
        $viewConfig = $this->createStub(ViewConfig::class);
        $viewConfig->method('cacheDirectory')->willReturn($cacheDir);
        $viewConfig->method('autoRefresh')->willReturn(true);
        $latteViewConfig = $this->createStub(LatteViewConfig::class);
        $latteViewConfig->method('strictTypes')->willReturn(true);

        $engine = new LatteEngineFactory(
            $viewConfig,
            $latteViewConfig,
            latteRouteExtension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
            new PathTemplateResolver(),
        )->create();
        $engine->setLoader(new StringLoader(['main' => '{route("shows.show", [id: 9])}']));
        $html = $engine->renderToString('main');
        array_map(unlink(...), glob($cacheDir . '/*'));
        rmdir($cacheDir);

        $extensions = array_filter(
            $engine->getExtensions(),
            fn (object $extension): bool => $extension instanceof TemplateFunctionExtension,
        );

        expect($extensions)->toHaveCount(1)
            ->and($html)->toBe('/shows/9');
    });
});
