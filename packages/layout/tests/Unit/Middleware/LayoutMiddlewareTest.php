<?php

declare(strict_types=1);

use Marko\Layout\Attributes\Component;
use Marko\Layout\Attributes\Layout;
use Marko\Layout\LayoutProcessorInterface;
use Marko\Layout\LayoutResolver;
use Marko\Layout\Middleware\LayoutMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\MatchedRoute;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcherInterface;

// Fixtures

#[Layout(component: LmFixtureRootComponent::class)]
class LmFixtureController
{
    public bool $actionInvoked = false;

    public function index(): string
    {
        $this->actionInvoked = true;
        return 'controller response';
    }
}

class LmFixtureNoLayoutController
{
    public function index(): string
    {
        return 'no layout response';
    }
}

#[Layout(component: LmFixtureRootComponent::class)]
class LmFixtureClassLayoutController
{
    public function show(string $id): string
    {
        return 'show ' . $id;
    }
}

class LmFixtureMethodLayoutController
{
    public function index(): string
    {
        return 'no layout';
    }

    #[Layout(component: LmFixtureRootComponent::class)]
    public function show(): string
    {
        return 'method layout';
    }
}

#[Component(template: 'layouts/root.html', slots: ['content'])]
class LmFixtureRootComponent {}

// Stub RouteMatcherInterface that always returns the given MatchedRoute
function stubMatcher(?MatchedRoute $matched): RouteMatcherInterface
{
    return new class ($matched) implements RouteMatcherInterface
    {
        public function __construct(private readonly ?MatchedRoute $stub) {}

        public function match(string $method, string $path): ?MatchedRoute
        {
            return $this->stub;
        }
    };
}

// Stub LayoutProcessorInterface that records calls and returns a fixed response
function stubProcessor(?Response $response = null): LayoutProcessorInterface
{
    return new class ($response ?? Response::html('<layout/>')) implements LayoutProcessorInterface
    {
        public ?string $calledController = null;

        public ?string $calledAction = null;

        public array $calledParameters = [];

        public function __construct(private readonly Response $stub) {}

        public function process(
            string $controllerClass,
            string $action,
            string $routePath,
            array $routeParameters,
            Request $request,
        ): Response {
            $this->calledController = $controllerClass;
            $this->calledAction = $action;
            $this->calledParameters = $routeParameters;

            return $this->stub;
        }
    };
}

function makeRoute(string $controller, string $action, string $path = '/test'): RouteDefinition
{
    return new RouteDefinition(
        method: 'GET',
        path: $path,
        controller: $controller,
        action: $action,
    );
}

function makeRequest(string $path = '/test'): Request
{
    return new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]);
}

describe('LayoutMiddleware', function (): void {
    it('delegates to LayoutProcessor when controller has Layout attribute', function (): void {
        $route = makeRoute(LmFixtureController::class, 'index');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor();

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();
        $next = fn (Request $r): Response => new Response('next called');

        $response = $middleware->handle($request, $next);

        expect($response->body())->toBe('<layout/>');
    });

    it('preserves existing behavior when controller has no Layout attribute', function (): void {
        $route = makeRoute(LmFixtureNoLayoutController::class, 'index');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor();

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();
        $next = fn (Request $r): Response => new Response('next called');

        $response = $middleware->handle($request, $next);

        expect($response->body())->toBe('next called');
    });

    it('passes route parameters to LayoutProcessor', function (): void {
        $route = makeRoute(LmFixtureController::class, 'index', '/products/{id}');
        $matched = new MatchedRoute($route, ['id' => '42']);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor();

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest('/products/42');
        $next = fn (Request $r): Response => new Response('next called');

        $middleware->handle($request, $next);

        expect($processor->calledParameters)->toBe(['id' => '42']);
    });

    it('handles method-level Layout attribute override', function (): void {
        // LmFixtureMethodLayoutController has no class-level #[Layout]
        // but the 'show' method has one — this should be delegated to LayoutProcessor
        $route = makeRoute(LmFixtureMethodLayoutController::class, 'show');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor();

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();
        $next = fn (Request $r): Response => new Response('next called');

        $response = $middleware->handle($request, $next);

        expect($response->body())->toBe('<layout/>')
            ->and($processor->calledController)->toBe(LmFixtureMethodLayoutController::class)
            ->and($processor->calledAction)->toBe('show');
    });

    it('still invokes the controller action for layout routes (for side effects)', function (): void {
        $route = makeRoute(LmFixtureController::class, 'index');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor();

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();
        $nextInvoked = false;
        $next = function (Request $r) use (&$nextInvoked): Response {
            $nextInvoked = true;

            return new Response('side effect result');
        };

        $middleware->handle($request, $next);

        expect($nextInvoked)->toBeTrue();
    });

    it('ignores controller return value when Layout is present', function (): void {
        $route = makeRoute(LmFixtureController::class, 'index');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor(Response::html('<layout-output/>'));

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();
        // The $next callable would normally return the controller's response
        $next = fn (Request $r): Response => new Response('controller output that should be ignored');

        $response = $middleware->handle($request, $next);

        // Response must come from LayoutProcessor, not from $next
        expect($response->body())->toBe('<layout-output/>');
    });

    it('works within the middleware pipeline', function (): void {
        // Middleware that runs before LayoutMiddleware — should see the request pass through
        $beforeCalled = false;

        $route = makeRoute(LmFixtureController::class, 'index');
        $matched = new MatchedRoute($route, []);
        $matcher = stubMatcher($matched);
        $processor = stubProcessor(Response::html('<pipeline/>'));

        $middleware = new LayoutMiddleware($matcher, $processor, new LayoutResolver());
        $request = makeRequest();

        // Simulate a pipeline: outer middleware wraps LayoutMiddleware
        $outerNext = function (Request $r) use ($middleware, &$beforeCalled): Response {
            $beforeCalled = true;

            return $middleware->handle($r, fn (Request $req): Response => new Response('inner next'));
        };

        $response = $outerNext($request);

        expect($beforeCalled)->toBeTrue()
            ->and($response->body())->toBe('<pipeline/>');
    });
});
