<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\Middleware\MiddlewarePipeline;

it('executes single middleware', function () {
    $executed = false;

    $middleware = new class ($executed) implements MiddlewareInterface
    {
        public function __construct(
            private bool &$executed,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->executed = true;

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $handler = fn (Request $r) => new Response('handler response');

    $response = $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: new Request(),
        handler: $handler,
    );

    expect($executed)->toBeTrue()
        ->and($response->body())->toBe('handler response');
});

it('executes multiple middleware in order', function () {
    $executionOrder = [];

    $middleware1 = new class ($executionOrder) implements MiddlewareInterface
    {
        public function __construct(
            private array &$executionOrder,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->executionOrder[] = 'middleware1';

            return $next($request);
        }
    };

    $middleware2 = new class ($executionOrder) implements MiddlewareInterface
    {
        public function __construct(
            private array &$executionOrder,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->executionOrder[] = 'middleware2';

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'Middleware1' => $middleware1,
            'Middleware2' => $middleware2,
        });

    $pipeline = new MiddlewarePipeline($container);
    $handler = fn (Request $r) => new Response('handler response');

    $response = $pipeline->process(
        middlewareClasses: ['Middleware1', 'Middleware2'],
        request: new Request(),
        handler: $handler,
    );

    expect($executionOrder)->toBe(['middleware1', 'middleware2'])
        ->and($response->body())->toBe('handler response');
});

it('passes request through middleware chain', function () {
    $receivedRequest = null;

    $middleware = new class ($receivedRequest) implements MiddlewareInterface
    {
        public function __construct(
            private ?Request &$receivedRequest,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->receivedRequest = $request;

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $originalRequest = new Request(
        server: ['REQUEST_METHOD' => 'POST'],
        query: ['foo' => 'bar'],
    );

    $handlerRequest = null;
    $handler = function (Request $r) use (&$handlerRequest) {
        $handlerRequest = $r;

        return new Response('handler response');
    };

    $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: $originalRequest,
        handler: $handler,
    );

    expect($receivedRequest)->toBe($originalRequest)
        ->and($handlerRequest)->toBe($originalRequest);
});

it('executes final handler after all middleware', function () {
    $executionOrder = [];

    $middleware1 = new class ($executionOrder) implements MiddlewareInterface
    {
        public function __construct(
            private array &$executionOrder,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->executionOrder[] = 'middleware1';

            return $next($request);
        }
    };

    $middleware2 = new class ($executionOrder) implements MiddlewareInterface
    {
        public function __construct(
            private array &$executionOrder,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->executionOrder[] = 'middleware2';

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'Middleware1' => $middleware1,
            'Middleware2' => $middleware2,
        });

    $pipeline = new MiddlewarePipeline($container);
    $handler = function (Request $r) use (&$executionOrder) {
        $executionOrder[] = 'handler';

        return new Response('handler response');
    };

    $pipeline->process(
        middlewareClasses: ['Middleware1', 'Middleware2'],
        request: new Request(),
        handler: $handler,
    );

    expect($executionOrder)->toBe(['middleware1', 'middleware2', 'handler']);
});

it('allows middleware to short-circuit by returning early', function () {
    $middleware2Called = false;
    $handlerCalled = false;

    $middleware1 = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            // Short-circuit by returning early without calling $next
            return new Response('Unauthorized', 401);
        }
    };

    $middleware2 = new class ($middleware2Called) implements MiddlewareInterface
    {
        public function __construct(
            private bool &$middleware2Called,
        ) {}

        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $this->middleware2Called = true;

            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturnCallback(fn (string $class) => match ($class) {
            'Middleware1' => $middleware1,
            'Middleware2' => $middleware2,
        });

    $pipeline = new MiddlewarePipeline($container);
    $handler = function (Request $r) use (&$handlerCalled) {
        $handlerCalled = true;

        return new Response('handler response');
    };

    $response = $pipeline->process(
        middlewareClasses: ['Middleware1', 'Middleware2'],
        request: new Request(),
        handler: $handler,
    );

    expect($middleware2Called)->toBeFalse()
        ->and($handlerCalled)->toBeFalse()
        ->and($response->body())->toBe('Unauthorized')
        ->and($response->statusCode())->toBe(401);
});

it('allows middleware to modify request before passing', function () {
    $handlerRequest = null;

    // Middleware creates a new request with modified query parameters
    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            // Create modified request with additional query param
            $modifiedRequest = new Request(
                server: ['REQUEST_METHOD' => 'GET'],
                query: ['modified' => 'true'],
            );

            return $next($modifiedRequest);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $originalRequest = new Request(
        server: ['REQUEST_METHOD' => 'GET'],
        query: ['original' => 'true'],
    );

    $handler = function (Request $r) use (&$handlerRequest) {
        $handlerRequest = $r;

        return new Response('handler response');
    };

    $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: $originalRequest,
        handler: $handler,
    );

    expect($handlerRequest)->not->toBe($originalRequest)
        ->and($handlerRequest->query('modified'))->toBe('true')
        ->and($handlerRequest->query('original'))->toBeNull();
});

it('allows middleware to modify response after receiving', function () {
    // Middleware wraps the response body with modified content
    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            $response = $next($request);

            // Create a modified response
            return new Response(
                body: 'MODIFIED: ' . $response->body(),
                statusCode: $response->statusCode(),
                headers: $response->headers(),
            );
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $handler = fn (Request $r) => new Response('original response');

    $response = $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: new Request(),
        handler: $handler,
    );

    expect($response->body())->toBe('MODIFIED: original response');
});

it('resolves middleware classes through container', function () {
    $resolvedClasses = [];

    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->exactly(2))
        ->method('get')
        ->willReturnCallback(function (string $class) use ($middleware, &$resolvedClasses) {
            $resolvedClasses[] = $class;

            return $middleware;
        });

    $pipeline = new MiddlewarePipeline($container);
    $handler = fn (Request $r) => new Response('handler response');

    $pipeline->process(
        middlewareClasses: ['App\\Middleware\\AuthMiddleware', 'App\\Middleware\\LogMiddleware'],
        request: new Request(),
        handler: $handler,
    );

    expect($resolvedClasses)->toBe([
        'App\\Middleware\\AuthMiddleware',
        'App\\Middleware\\LogMiddleware',
    ]);
});

it('handles empty middleware array (just runs handler)', function () {
    $handlerCalled = false;

    $container = $this->createMock(ContainerInterface::class);
    $container->expects($this->never())
        ->method('get');

    $pipeline = new MiddlewarePipeline($container);
    $handler = function (Request $r) use (&$handlerCalled) {
        $handlerCalled = true;

        return new Response('handler response');
    };

    $response = $pipeline->process(
        middlewareClasses: [],
        request: new Request(),
        handler: $handler,
    );

    expect($handlerCalled)->toBeTrue()
        ->and($response->body())->toBe('handler response');
});

it('propagates exceptions from middleware', function () {
    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            throw new RuntimeException('Middleware error');
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $handler = fn (Request $r) => new Response('handler response');

    expect(fn () => $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: new Request(),
        handler: $handler,
    ))->toThrow(RuntimeException::class, 'Middleware error');
});

it('propagates exceptions from handler', function () {
    $middleware = new class () implements MiddlewareInterface
    {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            return $next($request);
        }
    };

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
        ->willReturn($middleware);

    $pipeline = new MiddlewarePipeline($container);
    $handler = function (Request $r): Response {
        throw new RuntimeException('Handler error');
    };

    expect(fn () => $pipeline->process(
        middlewareClasses: ['TestMiddleware'],
        request: new Request(),
        handler: $handler,
    ))->toThrow(RuntimeException::class, 'Handler error');
});
