<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Middleware\CorsMiddleware;

function createCorsMockConfig(
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
            return isset($this->data[$key]);
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

function defaultCorsConfig(
    array $overrides = [],
): array {
    return array_merge([
        'security.cors.allowed_origins' => ['https://example.com'],
        'security.cors.allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'security.cors.allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN'],
        'security.cors.max_age' => 86400,
    ], $overrides);
}

describe('CorsMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('passes request through when no Origin header present', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->not->toHaveKey('Access-Control-Allow-Origin');
    });

    it('adds CORS headers for allowed origin', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->toHaveKey('Access-Control-Allow-Origin')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://example.com');
    });

    it('rejects request from disallowed origin', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://evil.com',
        ]);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK')
            ->and($response->headers())->not->toHaveKey('Access-Control-Allow-Origin');
    });

    it('handles preflight OPTIONS request with 204 response', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig());
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $nextCalled = false;
        $next = function (Request $r) use (&$nextCalled) {
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
        $config = createCorsMockConfig(defaultCorsConfig([
            'security.cors.allowed_origins' => ['*'],
        ]));
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'HTTP_ORIGIN' => 'https://any-site.com',
        ]);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->headers())->toHaveKey('Access-Control-Allow-Origin')
            ->and($response->headers()['Access-Control-Allow-Origin'])->toBe('https://any-site.com');
    });

    it('includes configured allowed methods and headers in preflight response', function (): void {
        $config = createCorsMockConfig(defaultCorsConfig([
            'security.cors.allowed_methods' => ['GET', 'POST'],
            'security.cors.allowed_headers' => ['Content-Type', 'Authorization'],
        ]));
        $middleware = new CorsMiddleware($config);

        $request = new Request(server: [
            'REQUEST_METHOD' => 'OPTIONS',
            'HTTP_ORIGIN' => 'https://example.com',
        ]);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->headers())->toHaveKey('Access-Control-Allow-Methods')
            ->and($response->headers()['Access-Control-Allow-Methods'])->toBe('GET, POST')
            ->and($response->headers())->toHaveKey('Access-Control-Allow-Headers')
            ->and($response->headers()['Access-Control-Allow-Headers'])->toBe('Content-Type, Authorization');
    });
});
