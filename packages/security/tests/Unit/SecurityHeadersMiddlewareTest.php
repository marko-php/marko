<?php

declare(strict_types=1);

use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Security\Tests\Helpers;
use Marko\Security\Tests\StreamingLikeResponse;
use Marko\Security\Tests\TaggedResponse;
use Marko\Testing\Fake\FakeConfigRepository;

describe('SecurityHeadersMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('adds all six security headers to response', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers)->toHaveKey('X-Content-Type-Options')
            ->and($headers['X-Content-Type-Options'])->toBe('nosniff')
            ->and($headers)->toHaveKey('X-Frame-Options')
            ->and($headers['X-Frame-Options'])->toBe('SAMEORIGIN')
            ->and($headers)->toHaveKey('X-XSS-Protection')
            ->and($headers['X-XSS-Protection'])->toBe('1; mode=block')
            ->and($headers)->toHaveKey('Strict-Transport-Security')
            ->and($headers['Strict-Transport-Security'])->toBe('max-age=31536000; includeSubDomains')
            ->and($headers)->toHaveKey('Referrer-Policy')
            ->and($headers['Referrer-Policy'])->toBe('strict-origin-when-cross-origin')
            ->and($headers)->toHaveKey('Content-Security-Policy')
            ->and($headers['Content-Security-Policy'])->toBe("default-src 'self'");
    });

    it('uses configured header values from SecurityConfig', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig([
            'security.headers.x_frame_options' => 'DENY',
            'security.headers.referrer_policy' => 'no-referrer',
        ]));
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers['X-Frame-Options'])->toBe('DENY')
            ->and($headers['Referrer-Policy'])->toBe('no-referrer');
    });

    it('omits headers with empty string config value', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig([
            'security.headers.x_xss_protection' => '',
            'security.headers.content_security_policy' => '',
        ]));
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers)->not->toHaveKey('X-XSS-Protection')
            ->and($headers)->not->toHaveKey('Content-Security-Policy')
            ->and($headers)->toHaveKey('X-Content-Type-Options')
            ->and($headers)->toHaveKey('X-Frame-Options')
            ->and($headers)->toHaveKey('Strict-Transport-Security')
            ->and($headers)->toHaveKey('Referrer-Policy');
    });

    it('preserves existing response headers', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200, [
            'Content-Type' => 'text/html',
            'X-Custom' => 'value',
        ]);

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers)->toHaveKey('Content-Type')
            ->and($headers['Content-Type'])->toBe('text/html')
            ->and($headers)->toHaveKey('X-Custom')
            ->and($headers['X-Custom'])->toBe('value')
            ->and($headers)->toHaveKey('X-Content-Type-Options');
    });

    it('preserves response body and status code', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('Hello World', 201, [
            'Content-Type' => 'text/plain',
        ]);

        $response = $middleware->handle($request, $next);

        expect($response->body())->toBe('Hello World')
            ->and($response->statusCode())->toBe(201);
    });

    it('builds from a SecurityConfig backed by FakeConfigRepository', function (): void {
        $repository = new FakeConfigRepository(Helpers::defaultHeadersConfig());
        $config = new SecurityConfig($repository);
        $middleware = new SecurityHeadersMiddleware($config);

        expect($repository)->toBeInstanceOf(FakeConfigRepository::class)
            ->and($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('preserves the response subclass through security headers middleware', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): TaggedResponse => Helpers::createTaggedResponse(tag: 'from-controller');

        $response = $middleware->handle($request, $next);

        /** @var TaggedResponse $response */
        expect($response)
            ->toBeInstanceOf(TaggedResponse::class)
            ->and($response->tag)->toBe('from-controller')
            ->and($response->headers())->toHaveKey('X-Frame-Options');
    });

    it(
        'preserves the streaming payload when a streaming response passes through security headers middleware',
        function (): void {
            $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig());
            $middleware = new SecurityHeadersMiddleware($config);

            $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
            $chunks = ['event: message', 'data: one', 'data: two'];
            $next = fn (Request $r): StreamingLikeResponse => Helpers::createStreamingLikeResponse($chunks);

            $response = $middleware->handle($request, $next);

            /** @var StreamingLikeResponse $response */
            expect($response)
                ->toBeInstanceOf(StreamingLikeResponse::class)
                ->and($response->chunks())->toBe($chunks)
                ->and($response->headers())->toHaveKey('X-Frame-Options');
        },
    );

    it('still applies the same header values after migrating to decoration', function (): void {
        $config = Helpers::createSecurityConfig(Helpers::defaultHeadersConfig([
            'security.headers.x_frame_options' => 'DENY',
        ]));
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r): Response => new Response('OK', 200, ['X-Custom' => 'value']);

        $response = $middleware->handle($request, $next);

        expect($response->headers())->toBe([
            'X-Custom' => 'value',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'",
        ]);
    });
});
