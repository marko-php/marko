<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\RoutingConfig;
use Marko\Routing\UrlGenerator;
use Marko\Routing\UrlGeneratorInterface;
use Marko\Testing\Fake\FakeConfigRepository;

function urlGeneratorRoutes(): RouteCollection
{
    $collection = new RouteCollection();
    $collection->add(new RouteDefinition(method: 'GET', path: '/', controller: 'C', action: 'home', name: 'home'));
    $collection->add(
        new RouteDefinition(
            method: 'GET',
            path: '/shows/{id:\d+}',
            controller: 'C',
            action: 'show',
            name: 'shows.show',
        ),
    );
    $collection->add(
        new RouteDefinition(method: 'GET', path: '/tags/{tag}', controller: 'C', action: 'tag', name: 'tags.show'),
    );
    $collection->add(
        new RouteDefinition(method: 'GET', path: '/docs/{path*}', controller: 'C', action: 'docs', name: 'docs'),
    );
    $collection->add(
        new RouteDefinition(
            method: 'GET',
            path: '/users/{user}/posts/{post}',
            controller: 'C',
            action: 'post',
            name: 'users.posts.show',
        ),
    );

    return $collection;
}

function urlGenerator(
    string $baseUrl = '',
): UrlGenerator {
    return new UrlGenerator(
        routeCollection: urlGeneratorRoutes(),
        routingConfig: new RoutingConfig(new FakeConfigRepository(['routing.url' => $baseUrl])),
    );
}

describe('UrlGenerator', function (): void {
    it('implements UrlGeneratorInterface', function (): void {
        expect(urlGenerator())->toBeInstanceOf(UrlGeneratorInterface::class);
    });

    it('generates a relative URL for a named route', function (): void {
        $generator = urlGenerator();

        expect($generator->route('shows.show', ['id' => 42]))->toBe('/shows/42')
            ->and($generator->route('home'))->toBe('/')
            ->and($generator->route('users.posts.show', ['user' => 'mark', 'post' => 'hello']))->toBe(
                '/users/mark/posts/hello',
            );
    });

    it('encodes parameter values', function (): void {
        expect(urlGenerator()->route('tags.show', ['tag' => 'c# & php 8?']))->toBe('/tags/c%23%20%26%20php%208%3F');
    });

    it('keeps slashes in catch-all values', function (): void {
        expect(urlGenerator()->route('docs', ['path' => '/guides/getting started/']))->toBe(
            '/docs/guides/getting%20started',
        );
    });

    it('appends unused parameters as a query string', function (): void {
        expect(urlGenerator()->route('tags.show', ['tag' => 'php', 'page' => 2, 'sort' => 'new est']))
            ->toBe('/tags/php?page=2&sort=new%20est');
    });

    it('casts int, float and bool parameter values to strings', function (): void {
        $generator = urlGenerator();

        expect($generator->route('tags.show', ['tag' => 1.5]))->toBe('/tags/1.5')
            ->and($generator->route('tags.show', ['tag' => true]))->toBe('/tags/1')
            ->and($generator->route('tags.show', ['tag' => false]))->toBe('/tags/0');
    });

    it('generates an absolute URL from the configured base URL', function (): void {
        expect(urlGenerator('https://example.com')->route('shows.show', ['id' => 7], absolute: true))
            ->toBe('https://example.com/shows/7');
    });

    it('joins a base URL with a trailing slash or sub-path without a double slash', function (): void {
        expect(urlGenerator('https://example.com/')->route('shows.show', ['id' => 7], absolute: true))
            ->toBe('https://example.com/shows/7')
            ->and(urlGenerator('https://example.com/app/')->route('shows.show', ['id' => 7], absolute: true))
            ->toBe('https://example.com/app/shows/7');
    });

    it('throws when an absolute URL is requested without a base URL', function (): void {
        urlGenerator()->route('home', absolute: true);
    })->throws(
        UrlGenerationException::class,
        "Cannot generate an absolute URL for route 'home': no base URL is configured",
    );

    it('throws when a required parameter is missing', function (): void {
        urlGenerator()->route('users.posts.show', ['user' => 'mark']);
    })->throws(UrlGenerationException::class, "Missing parameter 'post' for route 'users.posts.show'");

    it('throws when a required parameter is an empty string', function (): void {
        urlGenerator()->route('tags.show', ['tag' => '']);
    })->throws(UrlGenerationException::class, "Missing parameter 'tag' for route 'tags.show'");

    it('throws when a path parameter value is not a scalar', function (): void {
        urlGenerator()->route('tags.show', ['tag' => ['php']]);
    })->throws(
        UrlGenerationException::class,
        "Parameter 'tag' for route 'tags.show' must be a string, int, float or bool",
    );

    it('throws when a parameter violates its constraint', function (): void {
        urlGenerator()->route('shows.show', ['id' => 'abc']);
    })->throws(UrlGenerationException::class, "Parameter 'id' for route 'shows.show' does not match its constraint");

    it('throws when a value would not match the route back', function (string $name, array $parameters): void {
        urlGenerator()->route($name, $parameters);
    })->with([
        'slash in a segment' => ['tags.show', ['tag' => 'php/8']],
        'parent segment' => ['tags.show', ['tag' => '..']],
        'NUL byte' => ['tags.show', ['tag' => "php\0"]],
        'parent segment in a catch-all' => ['docs', ['path' => 'guides/../../secret']],
    ])->throws(UrlGenerationException::class, 'has an unsafe value');

    it('throws when the route name is unknown', function (): void {
        try {
            urlGenerator()->route('shows.shwo');
            $this->fail('Expected a UrlGenerationException');
        } catch (UrlGenerationException $exception) {
            expect($exception)->toBeInstanceOf(RouteException::class)
                ->and($exception->getMessage())->toContain("No route named 'shows.shwo'")
                ->and($exception->getSuggestion())->toContain("'shows.show'");
        }
    });

    it('generates URLs that the route matcher resolves back to the same parameters', function (): void {
        $routes = urlGeneratorRoutes();
        $generator = new UrlGenerator($routes, new RoutingConfig(new FakeConfigRepository(['routing.url' => ''])));
        $matcher = new RouteMatcher($routes);

        $tag = $matcher->match('GET', $generator->route('tags.show', ['tag' => 'c# & php 8 ü']));
        $docs = $matcher->match('GET', $generator->route('docs', ['path' => 'a b/c%d/e']));

        expect($tag->parameters)->toBe(['tag' => 'c# & php 8 ü'])
            ->and($docs->parameters)->toBe(['path' => 'a b/c%d/e']);
    });
});

describe('RoutingConfig', function (): void {
    it('reads the base URL from routing.url', function (): void {
        expect((new RoutingConfig(new FakeConfigRepository(['routing.url' => 'https://example.com'])))->url())
            ->toBe('https://example.com');
    });

    it('ships a routing config file that reads APP_URL', function (): void {
        $config = require dirname(__DIR__) . '/config/routing.php';

        expect($config)->toHaveKey('url')
            ->and(file_get_contents(dirname(__DIR__) . '/config/routing.php'))->toContain(
                "Env::string('APP_URL', '')",
            );
    });
});

describe('module.php', function (): void {
    it('binds UrlGeneratorInterface as a singleton in module.php', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['singletons'])->toMatchArray([UrlGeneratorInterface::class => UrlGenerator::class]);
    });

    it('requires marko/config', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__) . '/composer.json'), true);

        expect($composer['require'])->toHaveKey('marko/config');
    });
});
