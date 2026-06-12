<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RouteMatcher;
use Marko\Routing\Router;

it('accepts a RouteMatcher in constructor', function (): void {
    $routes = new RouteCollection();
    $container = $this->createMock(ContainerInterface::class);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    expect($router)->toBeInstanceOf(Router::class);
});

it('matches request to route', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/hello',
        controller: TestController::class,
        action: 'index',
    ));

    $controller = new class ()
    {
        public function index(): Response
        {
            return new Response('Hello World');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/hello',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toBe('Hello World');
});

it('returns 404 response when no route matches', function (): void {
    $routes = new RouteCollection();

    $container = $this->createMock(ContainerInterface::class);

    $router = new Router(
        matcher: new RouteMatcher($routes),
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

it('resolves controller through container', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/test',
        controller: 'App\\Controllers\\TestController',
        action: 'index',
    ));

    $controller = new class ()
    {
        public function index(): Response
        {
            return new Response('Resolved');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->once())
        ->method('get')
        ->with('App\\Controllers\\TestController')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/test',
    ]);

    $response = $router->handle($request);

    expect($response->body())->toBe('Resolved');
});

it('invokes controller method', function (): void {
    $methodCalled = false;

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/create',
        controller: 'App\\Controllers\\CreateController',
        action: 'store',
    ));

    $controller = new class ($methodCalled)
    {
        public function __construct(
            private bool &$methodCalled,
        ) {}

        public function store(): Response
        {
            $this->methodCalled = true;

            return new Response('Created', 201);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/create',
    ]);

    $router->handle($request);

    expect($methodCalled)->toBeTrue();
});

it('passes route parameters to controller method', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/users/{id}',
        controller: 'App\\Controllers\\UserController',
        action: 'show',
    ));

    $receivedId = null;
    $controller = new class ($receivedId)
    {
        public function __construct(
            private ?string &$receivedId,
        ) {}

        public function show(
            string $id,
        ): Response {
            $this->receivedId = $id;

            return new Response("User: $id");
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/users/42',
    ]);

    $response = $router->handle($request);

    expect($receivedId)->toBe('42')
        ->and($response->body())->toBe('User: 42');
});

it('executes middleware pipeline', function (): void {
    $middlewareExecuted = false;

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/protected',
        controller: 'App\\Controllers\\ProtectedController',
        action: 'index',
        middleware: ['App\\Middleware\\AuthMiddleware'],
    ));

    $controller = new class ()
    {
        public function index(): Response
        {
            return new Response('Protected content');
        }
    };

    $middleware = new class ($middlewareExecuted) implements MiddlewareInterface
    {
        public function __construct(
            private bool &$middlewareExecuted,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->middlewareExecuted = true;

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'App\\Controllers\\ProtectedController' => $controller,
            'App\\Middleware\\AuthMiddleware' => $middleware,
        });

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/protected',
    ]);

    $response = $router->handle($request);

    expect($middlewareExecuted)->toBeTrue()
        ->and($response->body())->toBe('Protected content');
});

it('returns response from controller', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/api/data',
        controller: 'App\\Controllers\\ApiController',
        action: 'getData',
    ));

    $controller = new class ()
    {
        public function getData(): Response
        {
            return new Response(
                body: '{"status":"success"}',
                statusCode: 200,
                headers: ['Content-Type' => 'application/json'],
            );
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/api/data',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toBe('{"status":"success"}')
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers())->toBe(['Content-Type' => 'application/json']);
});

it('returns response from middleware short-circuit', function (): void {
    $controllerCalled = false;

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/admin',
        controller: 'App\\Controllers\\AdminController',
        action: 'dashboard',
        middleware: ['App\\Middleware\\AuthMiddleware'],
    ));

    $controller = new class ($controllerCalled)
    {
        public function __construct(
            private bool &$controllerCalled,
        ) {}

        public function dashboard(): Response
        {
            $this->controllerCalled = true;

            return new Response('Admin Dashboard');
        }
    };

    // Middleware short-circuits by returning 401 without calling $next
    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            return new Response('Unauthorized', 401);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'App\\Controllers\\AdminController' => $controller,
            'App\\Middleware\\AuthMiddleware' => $middleware,
        });

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/admin',
    ]);

    $response = $router->handle($request);

    expect($controllerCalled)->toBeFalse()
        ->and($response->statusCode())->toBe(401)
        ->and($response->body())->toBe('Unauthorized');
});

it('handles controller returning Response object', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/redirect',
        controller: 'App\\Controllers\\RedirectController',
        action: 'redirectHome',
    ));

    $controller = new class ()
    {
        public function redirectHome(): Response
        {
            return Response::redirect('/home');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/redirect',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(302)
        ->and($response->headers())->toBe(['Location' => '/home']);
});

it('wraps string return in Response object', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/plain',
        controller: 'App\\Controllers\\PlainController',
        action: 'index',
    ));

    $controller = new class ()
    {
        public function index(): string
        {
            return 'Plain text response';
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/plain',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toBe('Plain text response')
        ->and($response->statusCode())->toBe(200);
});

it('wraps array return in JSON Response', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/api/users',
        controller: 'App\\Controllers\\ApiController',
        action: 'list',
    ));

    $controller = new class ()
    {
        /**
         * @return array<string, mixed>
         */
        public function list(): array
        {
            return ['users' => [['id' => 1, 'name' => 'John']]];
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/api/users',
    ]);

    $response = $router->handle($request);

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->body())->toBe('{"users":[{"id":1,"name":"John"}]}')
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers())->toBe(['Content-Type' => 'application/json']);
});

it('injects Request into controller method parameter', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/with-request',
        controller: 'App\\Controllers\\RequestController',
        action: 'index',
    ));

    $receivedRequest = null;
    $controller = new class ($receivedRequest)
    {
        public function __construct(
            private ?Request &$receivedRequest,
        ) {}

        public function index(
            Request $request,
        ): Response {
            $this->receivedRequest = $request;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/with-request',
    ]);

    $response = $router->handle($request);

    expect($receivedRequest)->toBeInstanceOf(Request::class)
        ->and($response->body())->toBe('OK');
});

it('executes global middleware on every request', function (): void {
    $globalExecuted = false;

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/hello',
        controller: 'App\\Controllers\\HelloController',
        action: 'index',
    ));

    $controller = new class ()
    {
        public function index(): Response
        {
            return new Response('Hello');
        }
    };

    $globalMiddleware = new class ($globalExecuted) implements MiddlewareInterface
    {
        public function __construct(
            private bool &$globalExecuted,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->globalExecuted = true;

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'App\\Controllers\\HelloController' => $controller,
            'App\\Middleware\\GlobalMiddleware' => $globalMiddleware,
        });

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: ['App\\Middleware\\GlobalMiddleware'],
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/hello',
    ]);

    $response = $router->handle($request);

    expect($globalExecuted)->toBeTrue()
        ->and($response->body())->toBe('Hello');
});

it('runs global middleware before route middleware', function (): void {
    $order = [];

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/test',
        controller: 'App\\Controllers\\TestCtrl',
        action: 'index',
        middleware: ['App\\Middleware\\RouteMiddleware'],
    ));

    $controller = new class ()
    {
        public function index(): Response
        {
            return new Response('OK');
        }
    };

    $globalMiddleware = new class ($order) implements MiddlewareInterface
    {
        public function __construct(
            private array &$order,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->order[] = 'global';

            return $next($request);
        }
    };

    $routeMiddleware = new class ($order) implements MiddlewareInterface
    {
        public function __construct(
            private array &$order,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->order[] = 'route';

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'App\\Controllers\\TestCtrl' => $controller,
            'App\\Middleware\\GlobalMiddleware' => $globalMiddleware,
            'App\\Middleware\\RouteMiddleware' => $routeMiddleware,
        });

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
        globalMiddleware: ['App\\Middleware\\GlobalMiddleware'],
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/test',
    ]);

    $router->handle($request);

    expect($order)->toBe(['global', 'route']);
});

it('makes the matched controller and action visible to middleware during Router::handle()', function (): void {
    $capturedController = null;
    $capturedAction = null;

    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/hello',
        controller: 'App\\Controllers\\HelloController',
        action: 'greet',
        middleware: ['App\\Middleware\\InspectMiddleware'],
    ));

    $controller = new class ()
    {
        public function greet(): Response
        {
            return new Response('Hello');
        }
    };

    $middleware = new class ($capturedController, $capturedAction) implements MiddlewareInterface
    {
        public function __construct(
            private ?string &$capturedController,
            private ?string &$capturedAction,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->capturedController = $request->controller();
            $this->capturedAction = $request->action();

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'App\\Controllers\\HelloController' => $controller,
            'App\\Middleware\\InspectMiddleware' => $middleware,
        });

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/hello',
    ]);

    $router->handle($request);

    expect($capturedController)->toBe('App\\Controllers\\HelloController')
        ->and($capturedAction)->toBe('greet');
});

it('prefers a route param over a POST value of the same name', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/users/{id}',
        controller: 'App\\Controllers\\UserController',
        action: 'update',
    ));

    $receivedId = null;
    $controller = new class ($receivedId)
    {
        public function __construct(
            private ?int &$receivedId,
        ) {}

        public function update(
            int $id,
        ): Response {
            $this->receivedId = $id;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users/99'],
        post: ['id' => '5'],
    );

    $router->handle($request);

    expect($receivedId)->toBe(99);
});

it('still injects the default value for an optional param that has one', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/items',
        controller: 'App\\Controllers\\ItemController',
        action: 'index',
    ));

    $receivedPage = null;
    $controller = new class ($receivedPage)
    {
        public function __construct(
            private ?int &$receivedPage,
        ) {}

        public function index(
            int $page = 1,
        ): Response {
            $this->receivedPage = $page;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items'],
    );

    $router->handle($request);

    expect($receivedPage)->toBe(1);
});

it('does not raise a TypeError when a required typed scalar param is missing', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/items',
        controller: 'App\\Controllers\\ItemController',
        action: 'index',
    ));

    $controller = new class ()
    {
        public function index(
            int $page,
        ): Response {
            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items'],
    );

    $threw = false;
    try {
        $router->handle($request);
    } catch (TypeError) {
        $threw = true;
    }

    expect($threw)->toBeFalse();
});

it('returns a 4xx response naming the parameter when a required typed scalar param is missing', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/items',
        controller: 'App\\Controllers\\ItemController',
        action: 'store',
    ));

    $controller = new class ()
    {
        public function store(
            int $count,
        ): Response {
            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/items'],
    );

    $response = $router->handle($request);

    expect($response->statusCode())->toBeGreaterThanOrEqual(400)
        ->and($response->statusCode())->toBeLessThan(500)
        ->and($response->body())->toContain('count');
});

it('casts a query-string value to a typed scalar action parameter', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/items',
        controller: 'App\\Controllers\\ItemController',
        action: 'index',
    ));

    $receivedPage = null;
    $controller = new class ($receivedPage)
    {
        public function __construct(
            private ?int &$receivedPage,
        ) {}

        public function index(
            int $page,
        ): Response {
            $this->receivedPage = $page;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/items'],
        query: ['page' => '3'],
    );

    $router->handle($request);

    expect($receivedPage)->toBe(3);
});

it('casts a POST value to a bool action parameter', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/toggle',
        controller: 'App\\Controllers\\ToggleController',
        action: 'store',
    ));

    $receivedActive = null;
    $controller = new class ($receivedActive)
    {
        public function __construct(
            private ?bool &$receivedActive,
        ) {}

        public function store(
            bool $active,
        ): Response {
            $this->receivedActive = $active;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/toggle'],
        post: ['active' => '1'],
    );

    $router->handle($request);

    expect($receivedActive)->toBeTrue();
});

it('casts a POST value to an int action parameter', function (): void {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'POST',
        path: '/items',
        controller: 'App\\Controllers\\ItemController',
        action: 'store',
    ));

    $receivedCount = null;
    $controller = new class ($receivedCount)
    {
        public function __construct(
            private ?int &$receivedCount,
        ) {}

        public function store(
            int $count,
        ): Response {
            $this->receivedCount = $count;

            return new Response('OK');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        matcher: new RouteMatcher($routes),
        container: $container,
    );

    $request = new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/items'],
        post: ['count' => '42'],
    );

    $router->handle($request);

    expect($receivedCount)->toBe(42);
});

class TestController
{
    public function index(): Response
    {
        return new Response('Hello World');
    }
}
