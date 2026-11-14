<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Cors\Middleware\CorsMiddleware;
use Marko\Cors\Tests\Helpers;
use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;

/**
 * @param array<string, string> $server
 */
function corsRequest(
    string $method,
    string $uri = '/',
    array $server = [],
): Request {
    return new Request(server: [
        'REQUEST_METHOD' => $method,
        'REQUEST_URI' => $uri,
        'HTTP_ORIGIN' => 'https://example.com',
        ...$server,
    ]);
}

/**
 * @noinspection PhpUnused - Action invoked by the router
 */
class CorsTestController
{
    public function store(): Response
    {
        return new Response('Created', 201);
    }
}

describe('module registration', function (): void {
    it('registers CorsMiddleware as global middleware', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['globalMiddleware'])->toBe([CorsMiddleware::class]);
    });

    it('runs before every other module that declares global middleware', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $declaring = [];

        foreach (glob(dirname(__DIR__, 2) . '/*/module.php') as $manifest) {
            $package = basename(dirname($manifest));

            if ($package === 'cors') {
                continue;
            }

            $config = require $manifest;

            if (
                is_array($config)
                && ($config['globalMiddleware'] ?? []) !== []
            ) {
                $declaring[] = "marko/$package";
            }
        }

        expect($declaring)->not->toBeEmpty()
            ->and(array_diff($declaring, $module['sequence']['before']))->toBeEmpty();
    });
});

describe('paths', function (): void {
    it('skips requests whose path is outside the configured paths', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig(paths: ['api/*']));
        $next = fn (Request $request): Response => new Response('OK');

        $outside = $middleware->handle(corsRequest('GET', '/web/page'), $next);
        $inside = $middleware->handle(corsRequest('GET', '/api/users?page=2'), $next);

        expect($outside->headers())->not->toHaveKey('Access-Control-Allow-Origin')
            ->and($inside->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });

    it('matches nested paths with a wildcard', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig(paths: ['api/*', 'health']));
        $next = fn (Request $request): Response => new Response('OK');

        expect($middleware->handle(corsRequest('GET', '/api/v1/users/7'), $next)->headers())
            ->toHaveKey('Access-Control-Allow-Origin')
            ->and($middleware->handle(corsRequest('GET', '/health'), $next)->headers())
            ->toHaveKey('Access-Control-Allow-Origin')
            ->and($middleware->handle(corsRequest('GET', '/healthz'), $next)->headers())
            ->not->toHaveKey('Access-Control-Allow-Origin');
    });
});

describe('headers', function (): void {
    it('emits Access-Control-Expose-Headers on actual requests', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig(exposeHeaders: ['X-Total-Count', 'X-Page']));

        $response = $middleware->handle(corsRequest('GET'), fn (Request $request): Response => new Response('OK'));

        expect($response->headers()['Access-Control-Expose-Headers'])->toBe('X-Total-Count, X-Page');
    });

    it('omits Access-Control-Expose-Headers when none are configured', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig());

        $response = $middleware->handle(corsRequest('GET'), fn (Request $request): Response => new Response('OK'));

        expect($response->headers())->not->toHaveKey('Access-Control-Expose-Headers');
    });

    it('sends Access-Control-Allow-Credentials on preflight when credentials are supported', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig(supportsCredentials: true));

        $response = $middleware->handle(
            corsRequest('OPTIONS', '/', ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST']),
            fn (Request $request): Response => new Response('OK'),
        );

        expect($response->statusCode())->toBe(204)
            ->and($response->headers()['Access-Control-Allow-Credentials'])->toBe('true');
    });

    it('appends Origin to an existing Vary header', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig());

        $response = $middleware->handle(
            corsRequest('GET'),
            fn (Request $request): Response => new Response('OK', 200, ['Vary' => 'Accept-Encoding']),
        );

        expect($response->headers()['Vary'])->toBe('Accept-Encoding, Origin');
    });
});

describe('preflight detection', function (): void {
    it('passes OPTIONS without Access-Control-Request-Method to the next handler', function (): void {
        $middleware = new CorsMiddleware(Helpers::createCorsConfig());

        $response = $middleware->handle(
            corsRequest('OPTIONS'),
            fn (Request $request): Response => new Response('', 204, ['Allow' => 'GET, HEAD, OPTIONS']),
        );

        expect($response->headers()['Allow'])->toBe('GET, HEAD, OPTIONS')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com')
            ->and($response->headers())->not->toHaveKey('Access-Control-Allow-Methods');
    });
});

function corsRouter(): Router
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/api/items',
        controller: CorsTestController::class,
        action: 'store',
    ));

    $container = new Container(new PreferenceRegistry());
    $container->instance(CorsMiddleware::class, new CorsMiddleware(Helpers::createCorsConfig(
        allowedMethods: ['GET', 'POST'],
        allowedHeaders: ['Content-Type', 'Authorization'],
    )));
    $container->instance(CorsTestController::class, new CorsTestController());

    return new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: [CorsMiddleware::class],
    );
}

describe('through the router', function (): void {
    it('answers a cross-origin preflight to a POST-only route with 204 and CORS headers', function (): void {
        $response = corsRouter()->handle(corsRequest('OPTIONS', '/api/items', [
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]));

        expect($response->statusCode())->toBe(204)
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com')
            ->and($response->headers()['Access-Control-Allow-Methods'])->toBe('GET, POST')
            ->and($response->headers()['Access-Control-Allow-Headers'])->toBe('Content-Type, Authorization');
    });

    it('adds CORS headers to a 405 response', function (): void {
        $response = corsRouter()->handle(corsRequest('GET', '/api/items'));

        expect($response->statusCode())->toBe(405)
            ->and($response->headers()['Allow'])->toBe('POST, OPTIONS')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });

    it('adds CORS headers to the actual cross-origin request', function (): void {
        $response = corsRouter()->handle(corsRequest('POST', '/api/items'));

        expect($response->statusCode())->toBe(201)
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });

    it('answers a preflight to an unknown covered path with 204 and CORS headers', function (): void {
        $response = corsRouter()->handle(corsRequest('OPTIONS', '/api/missing', [
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]));

        expect($response->statusCode())->toBe(204)
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com')
            ->and($response->headers()['Access-Control-Allow-Methods'])->toBe('GET, POST');
    });

    it('adds CORS headers to a 404 for a non-preflight request to an unknown path', function (): void {
        $response = corsRouter()->handle(corsRequest('GET', '/api/missing'));

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });
});

describe('unmatched requests', function (): void {
    it('declares RunsOnUnmatched so preflights reach it when no route matches', function (): void {
        $attributes = new ReflectionClass(CorsMiddleware::class)->getAttributes(RunsOnUnmatched::class);

        expect($attributes)->toHaveCount(1);
    });
});
