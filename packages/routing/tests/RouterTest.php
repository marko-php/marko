<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\Router;

it('accepts RouteCollection in constructor', function (): void {
    $routes = new RouteCollection();
    $container = $this->createMock(ContainerInterface::class);

    $router = new Router(
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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
            return Response::redirect('/home', 302);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($controller);

    $router = new Router(
        routes: $routes,
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
        routes: $routes,
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
        routes: $routes,
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

class TestController
{
    public function index(): Response
    {
        return new Response('Hello World');
    }
}
