<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Middleware\CorsMiddleware;
use Marko\Security\Tests\Helpers;
use Marko\Security\Tests\TaggedResponse;
use Marko\Testing\Fake\FakeConfigRepository;

describe('CorsMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('passes request through when no Origin header present', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->not->toHaveKey('Access-Control-Allow-Origin');
    });

    it('adds CORS headers for allowed origin', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->toHaveKey('Access-Control-Allow-Origin')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });

    it('rejects request from disallowed origin', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://evil.com',
        ]);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->not->toHaveKey('Access-Control-Allow-Origin');
    });

    it('handles preflight OPTIONS request with 204 response', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $nextCalled = false;
        $next = function (Request $r) use (&$nextCalled): Response {
            $nextCalled = true;

            return new Response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(204)
            ->and($response->body())->toBe('')
            ->and($nextCalled)->toBeFalse()
            ->and($response->headers())->toHaveKey('Access-Control-Allow-Origin')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com')
            ->and($response->headers())->toHaveKey('Access-Control-Max-Age')
            ->and($response->headers()['Access-Control-Max-Age'])->toBe('86400');
    });

    it('supports wildcard origin', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig([
            'security.cors.allowed_origins' => ['*'],
        ]));
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://any-site.com',
        ]);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->headers())->toHaveKey('Access-Control-Allow-Origin')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://any-site.com');
    });

    it('includes configured allowed methods and headers in preflight response', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig([
            'security.cors.allowed_methods' => ['GET', 'POST'],
            'security.cors.allowed_headers' => ['Content-Type', 'Authorization'],
        ]));
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->headers())->toHaveKey('Access-Control-Allow-Methods')
            ->and($response->headers()['Access-Control-Allow-Methods'])->toBe('GET, POST')
            ->and($response->headers())->toHaveKey('Access-Control-Allow-Headers')
            ->and($response->headers()['Access-Control-Allow-Headers'])->toBe('Content-Type, Authorization');
    });

    it('builds from a SecurityConfig backed by FakeConfigRepository', function (): void {
        $repository = new FakeConfigRepository(Helpers::defaultCorsConfig());
        $config = new SecurityConfig($repository);
        $middleware = new CorsMiddleware($config);

        expect($repository)->toBeInstanceOf(FakeConfigRepository::class)
            ->and($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('preserves the response subclass through the security package cors middleware', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $next = fn (Request $r): TaggedResponse => Helpers::createTaggedResponse(tag: 'from-controller');

        $response = $middleware->handle($request, $next);

        /** @var TaggedResponse $response */
        expect($response)
            ->toBeInstanceOf(TaggedResponse::class)
            ->and($response->tag)->toBe('from-controller')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });
});
