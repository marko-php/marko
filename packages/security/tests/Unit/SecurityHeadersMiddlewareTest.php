<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Middleware\SecurityHeadersMiddleware;

function createHeadersMockConfig(
    array $configData = [],
): SecurityConfig {
    $repo = new readonly class ($configData) implements ConfigRepositoryInterface
    {
        public function __construct(
            private array $data,
        ) {}

        public function get(
            string $key,
            ?string $scope = null,
        ): mixed {
            if (!$this->has($key, $scope)) {
                throw new ConfigNotFoundException($key);
            }

            return $this->data[$key];
        }

        public function has(
            string $key,
            ?string $scope = null,
        ): bool {
            return array_key_exists($key, $this->data);
        }

        public function getString(
            string $key,
            ?string $scope = null,
        ): string {
            return (string) $this->get($key, $scope);
        }

        public function getInt(
            string $key,
            ?string $scope = null,
        ): int {
            return (int) $this->get($key, $scope);
        }

        public function getBool(
            string $key,
            ?string $scope = null,
        ): bool {
            return (bool) $this->get($key, $scope);
        }

        public function getFloat(
            string $key,
            ?string $scope = null,
        ): float {
            return (float) $this->get($key, $scope);
        }

        public function getArray(
            string $key,
            ?string $scope = null,
        ): array {
            return (array) $this->get($key, $scope);
        }

        public function all(
            ?string $scope = null,
        ): array {
            return $this->data;
        }

        public function withScope(
            string $scope,
        ): ConfigRepositoryInterface {
            return $this;
        }
    };

    return new SecurityConfig($repo);
}

function defaultHeadersConfig(
    array $overrides = [],
): array {
    return array_merge([
        'security.headers.x_content_type_options' => 'nosniff',
        'security.headers.x_frame_options' => 'SAMEORIGIN',
        'security.headers.x_xss_protection' => '1; mode=block',
        'security.headers.strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'security.headers.referrer_policy' => 'strict-origin-when-cross-origin',
        'security.headers.content_security_policy' => "default-src 'self'",
    ], $overrides);
}

describe('SecurityHeadersMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $config = createHeadersMockConfig(defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('adds all six security headers to response', function (): void {
        $config = createHeadersMockConfig(defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200);

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
        $config = createHeadersMockConfig(defaultHeadersConfig([
            'security.headers.x_frame_options' => 'DENY',
            'security.headers.referrer_policy' => 'no-referrer',
        ]));
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers['X-Frame-Options'])->toBe('DENY')
            ->and($headers['Referrer-Policy'])->toBe('no-referrer');
    });

    it('omits headers with empty string config value', function (): void {
        $config = createHeadersMockConfig(defaultHeadersConfig([
            'security.headers.x_xss_protection' => '',
            'security.headers.content_security_policy' => '',
        ]));
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200);

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
        $config = createHeadersMockConfig(defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200, [
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
        $config = createHeadersMockConfig(defaultHeadersConfig());
        $middleware = new SecurityHeadersMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('Hello World', 201, [
            'Content-Type' => 'text/plain',
        ]);

        $response = $middleware->handle($request, $next);

        expect($response->body())->toBe('Hello World')
            ->and($response->statusCode())->toBe(201);
    });
});
