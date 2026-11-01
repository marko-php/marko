<?php

declare(strict_types=1);

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
use Marko\View\Twig\TemplateFunctionExtension;
use Marko\View\Twig\Tests\Fixtures\MarkupFunctionProvider;
use Marko\View\Twig\Tests\Fixtures\ViewProviders;
use Marko\View\Twig\TwigEngineFactory;
use Marko\View\Twig\TwigViewConfig;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\ArrayLoader;

function twigFunctionEnvironment(
    TemplateFunctionExtension $extension,
    string $template,
): Environment {
    $environment = new Environment(new ArrayLoader(['main' => $template]), ['autoescape' => 'html']);
    $environment->addExtension($extension);

    return $environment;
}

function twigRouteExtension(): TemplateFunctionExtension
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
        $html = twigFunctionEnvironment(
            ViewProviders::extension([MarkupFunctionProvider::class]),
            '{{ escaped("x") }}',
        )
            ->render('main');

        expect($html)->toBe('&lt;b&gt;x&lt;/b&gt;');
    });

    it('prints the output of a safe HTML function unescaped', function (): void {
        $html = twigFunctionEnvironment(ViewProviders::extension([MarkupFunctionProvider::class]), '{{ raw("x") }}')
            ->render('main');

        expect($html)->toBe('<b>x</b>');
    });

    it('registers no functions when no provider is discovered', function (): void {
        expect(ViewProviders::extension()->getFunctions())->toBe([]);
    });

    describe('route()', function (): void {
        it('renders a route URL', function (): void {
            $html = twigFunctionEnvironment(twigRouteExtension(), '{{ route("shows.show", {id: 5}) }}')
                ->render('main');

            expect($html)->toBe('/shows/5');
        });

        it('passes parameters and the absolute flag to the URL generator', function (): void {
            $html = twigFunctionEnvironment(
                twigRouteExtension(),
                '{{ route("shows.show", {id: 5, tab: "cast"}, true) }}',
            )->render('main');

            expect($html)->toBe('https://example.com/shows/5?tab=cast');
        });

        it('escapes the generated URL', function (): void {
            $html = twigFunctionEnvironment(
                twigRouteExtension(),
                '<a href="{{ route("shows.show", {id: 5, a: 1, b: 2}) }}">x</a>',
            )->render('main');

            expect($html)->toBe('<a href="/shows/5?a=1&amp;b=2">x</a>');
        });

        it('lets a URL generation exception fail the render', function (): void {
            try {
                twigFunctionEnvironment(twigRouteExtension(), '{{ route("shows.missing") }}')->render('main');
                $this->fail('Expected the render to fail');
            } catch (RuntimeError $error) {
                expect($error->getPrevious())->toBeInstanceOf(UrlGenerationException::class)
                    ->and($error->getPrevious()->getMessage())->toContain("No route named 'shows.missing'");
            }
        });
    });

    it('is registered on the environment by TwigEngineFactory', function (): void {
        $cacheDir = sys_get_temp_dir() . '/twig-functions-' . bin2hex(random_bytes(4));
        $config = $this->createStub(TwigViewConfig::class);
        $config->method('cacheDirectory')->willReturn($cacheDir);
        $config->method('autoRefresh')->willReturn(true);
        $config->method('strictVariables')->willReturn(true);
        $config->method('autoescape')->willReturn('html');
        $config->method('debug')->willReturn(false);
        $config->method('charset')->willReturn('UTF-8');

        $environment = new TwigEngineFactory(
            $config,
            twigRouteExtension(),
            new CacheDirectoryGuard(new ProjectPaths(sys_get_temp_dir())),
        )->create();
        $environment->setLoader(new ArrayLoader(['main' => '{{ route("shows.show", {id: 9}) }}']));
        $html = $environment->render('main');

        expect($environment->hasExtension(TemplateFunctionExtension::class))->toBeTrue()
            ->and($html)->toBe('/shows/9');
    });
});
