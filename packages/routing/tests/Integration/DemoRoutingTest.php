<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;

/**
 * Tests simulating demo/public/index.php behavior.
 * These tests verify the routing functionality that the demo app uses.
 */

it('demo index.php creates Request from globals', function (): void {
    // Simulating Request::fromGlobals() behavior
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/blog';
    $_GET = [];
    $_POST = [];

    $request = Request::fromGlobals();

    expect($request)->toBeInstanceOf(Request::class)
        ->and($request->method())->toBe('GET')
        ->and($request->path())->toBe('/blog');
});

it('demo index.php routes request through Router', function (): void {
    $controller = new class () {
        public function index(): Response
        {
            return new Response('Blog Index: Route matched successfully');
        }
    };

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/blog',
        controller: $controller::class,
        action: 'index',
    ));

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);
    $container->instance($controller::class, $controller);

    $router = new Router(
        routes: $routes,
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/blog',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toContain('Blog Index');
});

it('demo index.php sends Response to client', function (): void {
    $response = new Response('Test content', 200, ['X-Test' => 'Header']);

    // Test the Response object properties (send() would output to client)
    expect($response->body())->toBe('Test content')
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers())->toBe(['X-Test' => 'Header']);
});

it('demo index.php returns 404 for unmatched routes', function (): void {
    $controller = new class () {
        public function index(): Response
        {
            return new Response('Blog Index');
        }
    };

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/blog',
        controller: $controller::class,
        action: 'index',
    ));

    $preferenceRegistry = new PreferenceRegistry();
    $container = new Container($preferenceRegistry);

    $router = new Router(
        routes: $routes,
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/nonexistent',
    ]);

    $response = $router->handle($request);

    expect($response->statusCode())->toBe(404)
        ->and($response->body())->toBe('Not Found');
});
