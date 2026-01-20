<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

it('defines handle method signature', function () {
    $reflection = new ReflectionClass(MiddlewareInterface::class);

    expect($reflection->isInterface())->toBeTrue()
        ->and($reflection->hasMethod('handle'))->toBeTrue();
});

it('handle receives Request and next callable', function () {
    $reflection = new ReflectionMethod(MiddlewareInterface::class, 'handle');
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(2)
        ->and($parameters[0]->getName())->toBe('request')
        ->and($parameters[0]->getType()->getName())->toBe(Request::class)
        ->and($parameters[1]->getName())->toBe('next')
        ->and($parameters[1]->getType()->getName())->toBe('callable');
});

it('handle returns Response', function () {
    $reflection = new ReflectionMethod(MiddlewareInterface::class, 'handle');
    $returnType = $reflection->getReturnType();

    expect($returnType)->not->toBeNull()
        ->and($returnType->getName())->toBe(Response::class);
});

it('middleware can call next to continue pipeline', function () {
    $middleware = new class () implements MiddlewareInterface {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            // Middleware passes through to next handler
            return $next($request);
        }
    };

    $request = new Request();
    $expectedResponse = new Response('Hello World');

    $response = $middleware->handle($request, fn (Request $r) => $expectedResponse);

    expect($response)->toBe($expectedResponse);
});

it('middleware can return early to short-circuit', function () {
    $nextCalled = false;

    $middleware = new class () implements MiddlewareInterface {
        public function handle(
            Request $request,
            callable $next,
        ): Response {
            // Middleware returns early without calling next
            return new Response('Unauthorized', 401);
        }
    };

    $request = new Request();

    $response = $middleware->handle($request, function (Request $r) use (&$nextCalled) {
        $nextCalled = true;

        return new Response('Should not reach here');
    });

    expect($nextCalled)->toBeFalse()
        ->and($response->body())->toBe('Unauthorized')
        ->and($response->statusCode())->toBe(401);
});
