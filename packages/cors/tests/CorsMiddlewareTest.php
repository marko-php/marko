<?php

declare(strict_types=1);

use Marko\Cors\Config\CorsConfig;
use Marko\Cors\Middleware\CorsMiddleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeConfigRepository;

it('adds Access-Control-Allow-Origin header for allowed origins', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['https://example.com'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => false,
        'cors.max_age' => 0,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_ORIGIN' => 'https://example.com',
    ]);

    $next = fn (Request $req): Response => new Response(body: 'OK');

    $response = $middleware->handle($request, $next);

    expect($response->headers())->toHaveKey('Access-Control-Allow-Origin')
        ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
});

it('handles preflight OPTIONS requests with 204 No Content response', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['https://example.com'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => false,
        'cors.max_age' => 0,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'OPTIONS',
        'HTTP_ORIGIN' => 'https://example.com',
    ]);

    $nextCalled = false;
    $next = function (
        Request $req,
    ) use (&$nextCalled): Response {
        $nextCalled = true;

        return new Response(body: 'OK');
    };

    $response = $middleware->handle($request, $next);

    expect($response->statusCode())->toBe(204)
        ->and($nextCalled)->toBeFalse()
        ->and($response->headers())->toHaveKey('Access-Control-Allow-Origin')
        ->and($response->headers())->toHaveKey('Access-Control-Allow-Methods')
        ->and($response->headers())->toHaveKey('Access-Control-Allow-Headers');
});

it('rejects requests from origins not in allowed list', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['https://example.com'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => false,
        'cors.max_age' => 0,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_ORIGIN' => 'https://evil.com',
    ]);

    $next = fn (Request $req): Response => new Response(body: 'OK');

    $response = $middleware->handle($request, $next);

    expect($response->headers())->not->toHaveKey('Access-Control-Allow-Origin');
});

it('reads CORS configuration from config/cors.php via CorsConfig', function (): void {
    $configFile = require dirname(__DIR__) . '/config/cors.php';

    expect($configFile)->toHaveKey('allowed_origins')
        ->and($configFile)->toHaveKey('allowed_methods')
        ->and($configFile)->toHaveKey('allowed_headers')
        ->and($configFile)->toHaveKey('expose_headers')
        ->and($configFile)->toHaveKey('supports_credentials')
        ->and($configFile)->toHaveKey('max_age');

    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => $configFile['allowed_origins'],
        'cors.allowed_methods' => $configFile['allowed_methods'],
        'cors.allowed_headers' => $configFile['allowed_headers'],
        'cors.expose_headers' => $configFile['expose_headers'],
        'cors.supports_credentials' => $configFile['supports_credentials'],
        'cors.max_age' => $configFile['max_age'],
    ]));

    expect($config->allowedOrigins())->toBe($configFile['allowed_origins'])
        ->and($config->allowedMethods())->toBe($configFile['allowed_methods'])
        ->and($config->allowedHeaders())->toBe($configFile['allowed_headers'])
        ->and($config->exposeHeaders())->toBe($configFile['expose_headers'])
        ->and($config->supportsCredentials())->toBe($configFile['supports_credentials'])
        ->and($config->maxAge())->toBe($configFile['max_age']);
});

it('supports wildcard star origin matching', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['*'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => false,
        'cors.max_age' => 0,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_ORIGIN' => 'https://any-origin.com',
    ]);

    $next = fn (Request $req): Response => new Response(body: 'OK');

    $response = $middleware->handle($request, $next);

    expect($response->headers())->toHaveKey('Access-Control-Allow-Origin')
        ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://any-origin.com');
});

it('includes Access-Control-Allow-Credentials header when configured', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['https://example.com'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => true,
        'cors.max_age' => 0,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'HTTP_ORIGIN' => 'https://example.com',
    ]);

    $next = fn (Request $req): Response => new Response(body: 'OK');

    $response = $middleware->handle($request, $next);

    expect($response->headers())->toHaveKey('Access-Control-Allow-Credentials')
        ->and($response->headers()['Access-Control-Allow-Credentials'])->toBe('true');
});

it('sets Access-Control-Max-Age header for preflight caching', function (): void {
    $config = new CorsConfig(new FakeConfigRepository([
        'cors.allowed_origins' => ['https://example.com'],
        'cors.allowed_methods' => ['GET', 'POST'],
        'cors.allowed_headers' => ['Content-Type'],
        'cors.expose_headers' => [],
        'cors.supports_credentials' => false,
        'cors.max_age' => 3600,
    ]));

    $middleware = new CorsMiddleware($config);

    $request = new Request(server: [
        'REQUEST_METHOD' => 'OPTIONS',
        'HTTP_ORIGIN' => 'https://example.com',
    ]);

    $next = fn (Request $req): Response => new Response(body: 'OK');

    $response = $middleware->handle($request, $next);

    expect($response->statusCode())->toBe(204)
        ->and($response->headers())->toHaveKey('Access-Control-Max-Age')
        ->and($response->headers()['Access-Control-Max-Age'])->toBe('3600');
});
