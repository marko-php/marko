<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Attributes\RunsOnUnmatched;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;

/**
 * Controller used by the router matching tests (resolved through a stub container).
 *
 * @noinspection PhpUnused - Actions are invoked by the router
 */
class MatchingTestController
{
    /** @var array<int, string> */
    public array $calls = [];

    public function show(): Response
    {
        $this->calls[] = 'show';

        return new Response('Show body', 200, ['X-Show' => 'yes', 'Content-Type' => 'text/plain']);
    }

    public function head(): Response
    {
        $this->calls[] = 'head';

        return new Response('', 200, ['X-Explicit-Head' => 'yes']);
    }

    public function options(): Response
    {
        $this->calls[] = 'options';

        return new Response('', 200, ['X-Explicit-Options' => 'yes']);
    }

    public function store(): Response
    {
        $this->calls[] = 'store';

        return new Response('Stored', 201);
    }
}

/**
 * Global middleware that stamps every response and records the controller it saw.
 */
class StampingMiddleware implements MiddlewareInterface
{
    /** @var array<int, ?string> */
    public array $controllers = [];

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->controllers[] = $request->controller();

        return $next($request)->withHeader('X-Global', 'ran');
    }
}

/**
 * Global middleware that opts in to unmatched (404/405/automatic OPTIONS) requests.
 */
#[RunsOnUnmatched]
class UnmatchedStampingMiddleware implements MiddlewareInterface
{
    /** @var array<int, ?string> */
    public array $controllers = [];

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $this->controllers[] = $request->controller();

        return $next($request)->withHeader('X-Unmatched', 'ran');
    }
}

/**
 * @param array<int, RouteDefinition> $routes
 * @param array<int, class-string<MiddlewareInterface>> $globalMiddleware
 * @return array{router: Router, controller: MatchingTestController, middleware: StampingMiddleware, unmatchedMiddleware: UnmatchedStampingMiddleware}
 */
function createMatchingRouter(
    array $routes,
    array $globalMiddleware = [],
): array {
    $collection = new RouteCollection();

    foreach ($routes as $route) {
        $collection->add($route);
    }

    $controller = new MatchingTestController();
    $middleware = new StampingMiddleware();
    $unmatchedMiddleware = new UnmatchedStampingMiddleware();
    $container = new readonly class ($controller, $middleware, $unmatchedMiddleware) implements ContainerInterface
    {
        public function __construct(
            private MatchingTestController $controller,
            private StampingMiddleware $middleware,
            private UnmatchedStampingMiddleware $unmatchedMiddleware,
        ) {}

        public function get(
            string $id,
        ): object {
            return match ($id) {
                MatchingTestController::class => $this->controller,
                StampingMiddleware::class => $this->middleware,
                UnmatchedStampingMiddleware::class => $this->unmatchedMiddleware,
            };
        }

        public function has(
            string $id,
        ): bool {
            return in_array(
                $id,
                [MatchingTestController::class, StampingMiddleware::class, UnmatchedStampingMiddleware::class],
                true,
            );
        }

        public function singleton(string $id): void {}

        public function instance(
            string $id,
            object $instance,
        ): void {}

        public function call(
            Closure $callable,
        ): mixed {
            return $callable();
        }

        public function resolvedInstances(
            ?string $interface = null,
        ): array {
            return [];
        }
    };

    return [
        'router' => new Router(
            matcher: new RouteMatcher($collection),
            container: $container,
            globalMiddleware: $globalMiddleware,
        ),
        'controller' => $controller,
        'middleware' => $middleware,
        'unmatchedMiddleware' => $unmatchedMiddleware,
    ];
}

function matchingRoute(
    string $method,
    string $path,
    string $action,
): RouteDefinition {
    return new RouteDefinition(
        method: $method,
        path: $path,
        controller: MatchingTestController::class,
        action: $action,
    );
}

/**
 * @param array<string, string> $headers
 */
function matchingRequest(
    string $method,
    string $uri,
    array $headers = [],
): Request {
    $server = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri];

    foreach ($headers as $name => $value) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return new Request(server: $server);
}

describe('unmatched requests', function (): void {
    it('returns 405 with an Allow header when the path matches another method', function (): void {
        ['router' => $router] = createMatchingRouter([matchingRoute('GET', '/posts/{id}', 'show')]);

        $response = $router->handle(matchingRequest('POST', '/posts/1'));

        expect($response->statusCode())->toBe(405)
            ->and($response->headers()['Allow'])->toBe('GET, HEAD, OPTIONS');
    });

    it('returns a JSON 404 for an unknown path when the client accepts JSON', function (): void {
        ['router' => $router] = createMatchingRouter([matchingRoute('GET', '/posts', 'show')]);

        $response = $router->handle(matchingRequest('GET', '/missing', ['Accept' => 'application/json']));

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('application/json')
            ->and(json_decode($response->body(), true))->toBe(['message' => 'Not Found']);
    });

    it('returns an HTML 404 for an unknown path by default', function (): void {
        ['router' => $router] = createMatchingRouter([]);

        $response = $router->handle(matchingRequest('GET', '/missing'));

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['Content-Type'])->toBe('text/html; charset=utf-8')
            ->and($response->body())->toContain('404 Not Found');
    });

    it('runs global middleware marked with RunsOnUnmatched on 404 and 405 responses', function (): void {
        ['router' => $router] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
            [UnmatchedStampingMiddleware::class],
        );

        $notFound = $router->handle(matchingRequest('GET', '/missing'));
        $notAllowed = $router->handle(matchingRequest('DELETE', '/posts'));

        expect($notFound->statusCode())->toBe(404)
            ->and($notFound->headers()['X-Unmatched'])->toBe('ran')
            ->and($notAllowed->statusCode())->toBe(405)
            ->and($notAllowed->headers()['X-Unmatched'])->toBe('ran');
    });

    it('skips global middleware without RunsOnUnmatched for unmatched requests', function (): void {
        ['router' => $router, 'middleware' => $middleware] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
            [StampingMiddleware::class, UnmatchedStampingMiddleware::class],
        );

        $notFound = $router->handle(matchingRequest('GET', '/missing'));
        $notAllowed = $router->handle(matchingRequest('DELETE', '/posts'));

        expect($notFound->statusCode())->toBe(404)
            ->and($notFound->headers())->not->toHaveKey('X-Global')
            ->and($notFound->headers()['X-Unmatched'])->toBe('ran')
            ->and($notAllowed->statusCode())->toBe(405)
            ->and($notAllowed->headers())->not->toHaveKey('X-Global')
            ->and($middleware->controllers)->toBeEmpty();
    });

    it('skips a global middleware whose declared class does not exist on unmatched requests', function (): void {
        ['router' => $router] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
            ['App\\Middleware\\MissingMiddleware', UnmatchedStampingMiddleware::class],
        );

        $response = $router->handle(matchingRequest('GET', '/missing'));

        expect($response->statusCode())->toBe(404)
            ->and($response->headers()['X-Unmatched'])->toBe('ran');
    });

    it('still runs every global middleware for matched routes', function (): void {
        ['router' => $router] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
            [StampingMiddleware::class, UnmatchedStampingMiddleware::class],
        );

        $response = $router->handle(matchingRequest('GET', '/posts'));

        expect($response->statusCode())->toBe(200)
            ->and($response->headers()['X-Global'])->toBe('ran')
            ->and($response->headers()['X-Unmatched'])->toBe('ran');
    });

    it('does not run route middleware for unmatched requests', function (): void {
        ['router' => $router, 'middleware' => $middleware] = createMatchingRouter([
            new RouteDefinition(
                method: 'GET',
                path: '/posts',
                controller: MatchingTestController::class,
                action: 'show',
                middleware: [StampingMiddleware::class],
            ),
        ]);

        $response = $router->handle(matchingRequest('POST', '/posts'));

        expect($response->statusCode())->toBe(405)
            ->and($response->headers())->not->toHaveKey('X-Global')
            ->and($middleware->controllers)->toBeEmpty();
    });

    it('matches request methods case-insensitively', function (): void {
        ['router' => $router, 'controller' => $controller] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
        );

        $response = $router->handle(matchingRequest('head', '/posts'));

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('')
            ->and($controller->calls)->toBe(['show']);
    });

    it('leaves the request controller null for unmatched requests', function (): void {
        ['router' => $router, 'unmatchedMiddleware' => $middleware] = createMatchingRouter(
            [],
            [UnmatchedStampingMiddleware::class],
        );

        $router->handle(matchingRequest('GET', '/missing'));

        expect($middleware->controllers)->toBe([null]);
    });
});

describe('OPTIONS', function (): void {
    it('answers OPTIONS automatically with 204 and an Allow header', function (): void {
        ['router' => $router] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show'), matchingRoute('POST', '/posts', 'store')],
            [UnmatchedStampingMiddleware::class],
        );

        $response = $router->handle(matchingRequest('OPTIONS', '/posts'));

        expect($response->statusCode())->toBe(204)
            ->and($response->headers()['Allow'])->toBe('GET, HEAD, POST, OPTIONS')
            ->and($response->headers()['X-Unmatched'])->toBe('ran')
            ->and($response->body())->toBe('');
    });

    it('answers automatic OPTIONS without running unmarked global middleware', function (): void {
        ['router' => $router, 'middleware' => $middleware] = createMatchingRouter(
            [matchingRoute('GET', '/posts', 'show')],
            [StampingMiddleware::class],
        );

        $response = $router->handle(matchingRequest('OPTIONS', '/posts'));

        expect($response->statusCode())->toBe(204)
            ->and($response->headers())->not->toHaveKey('X-Global')
            ->and($middleware->controllers)->toBeEmpty();
    });

    it('returns 404 for OPTIONS on an unknown path', function (): void {
        ['router' => $router] = createMatchingRouter([matchingRoute('GET', '/posts', 'show')]);

        expect($router->handle(matchingRequest('OPTIONS', '/missing'))->statusCode())->toBe(404);
    });

    it('dispatches an explicit OPTIONS route instead of the automatic response', function (): void {
        ['router' => $router, 'controller' => $controller] = createMatchingRouter([
            matchingRoute('GET', '/posts', 'show'),
            matchingRoute('OPTIONS', '/posts', 'options'),
        ]);

        $response = $router->handle(matchingRequest('OPTIONS', '/posts'));

        expect($response->statusCode())->toBe(200)
            ->and($response->headers())->toHaveKey('X-Explicit-Options')
            ->and($controller->calls)->toBe(['options']);
    });
});

describe('HEAD', function (): void {
    it('returns the GET status and headers with an empty body for HEAD', function (): void {
        ['router' => $router, 'controller' => $controller] = createMatchingRouter(
            [matchingRoute('GET', '/posts/{id}', 'show')],
        );

        $get = $router->handle(matchingRequest('GET', '/posts/1'));
        $head = $router->handle(matchingRequest('HEAD', '/posts/1'));

        expect($head->statusCode())->toBe($get->statusCode())
            ->and($head->headers())->toBe($get->headers())
            ->and($head->body())->toBe('')
            ->and($head->isBodyOmitted())->toBeTrue()
            ->and($controller->calls)->toBe(['show', 'show']);
    });

    it('dispatches an explicit HEAD route instead of the GET fallback', function (): void {
        ['router' => $router, 'controller' => $controller] = createMatchingRouter([
            matchingRoute('GET', '/posts', 'show'),
            matchingRoute('HEAD', '/posts', 'head'),
        ]);

        $response = $router->handle(matchingRequest('HEAD', '/posts'));

        expect($response->headers())->toHaveKey('X-Explicit-Head')
            ->and($controller->calls)->toBe(['head']);
    });

    it('strips the body from a HEAD error response', function (): void {
        ['router' => $router] = createMatchingRouter([matchingRoute('POST', '/posts', 'store')]);

        $missing = $router->handle(matchingRequest('HEAD', '/missing'));
        $notAllowed = $router->handle(matchingRequest('HEAD', '/posts'));

        expect($missing->statusCode())->toBe(404)
            ->and($missing->body())->toBe('')
            ->and($notAllowed->statusCode())->toBe(405)
            ->and($notAllowed->headers()['Allow'])->toBe('POST, OPTIONS')
            ->and($notAllowed->body())->toBe('');
    });
});

describe('#[RunsOnUnmatched]', function (): void {
    it('targets classes only', function (): void {
        $flags = new ReflectionClass(RunsOnUnmatched::class)->getAttributes(Attribute::class)[0]
            ->newInstance()->flags;

        expect($flags)->toBe(Attribute::TARGET_CLASS);
    });
});
