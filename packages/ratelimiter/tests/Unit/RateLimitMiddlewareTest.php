<?php

declare(strict_types=1);

use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Middleware\RateLimitMiddleware;
use Marko\RateLimiter\RateLimitResult;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

function createMockLimiter(
    RateLimitResult $result,
): RateLimiterInterface {
    return new readonly class ($result) implements RateLimiterInterface
    {
        public function __construct(
            private RateLimitResult $result,
        ) {}

        public function attempt(
            string $key,
            int $maxAttempts,
            int $decaySeconds,
        ): RateLimitResult {
            return $this->result;
        }

        public function tooManyAttempts(
            string $key,
            int $maxAttempts,
        ): bool {
            return !$this->result->allowed();
        }

        public function clear(
            string $key,
        ): void {}
    };
}

describe('RateLimitMiddleware', function (): void {
    it('implements MiddlewareInterface', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: true,
            remaining: 59,
        ));

        $middleware = new RateLimitMiddleware($limiter);

        expect($middleware)->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('allows requests within rate limit', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: true,
            remaining: 59,
        ));

        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '192.168.1.1']);
        $next = fn (Request $r) => new Response('OK', 200);

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(200)
            ->and($response->body())->toBe('OK');
    });

    it('passes request to next handler when allowed', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: true,
            remaining: 59,
        ));

        $nextCalled = false;
        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '10.0.0.1']);
        $next = function (Request $r) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        };

        $middleware->handle($request, $next);

        expect($nextCalled)->toBeTrue();
    });

    it('returns 429 response when rate limited', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: false,
            remaining: 0,
            retryAfter: 30,
        ));

        $nextCalled = false;
        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '10.0.0.1']);
        $next = function (Request $r) use (&$nextCalled) {
            $nextCalled = true;

            return new Response('OK');
        };

        $response = $middleware->handle($request, $next);

        expect($response->statusCode())->toBe(429)
            ->and($nextCalled)->toBeFalse()
            ->and($response->body())->toContain('Too Many Requests');
    });

    it('includes rate limit headers on allowed response', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: true,
            remaining: 42,
        ));

        $middleware = new RateLimitMiddleware(
            limiter: $limiter,
            maxAttempts: 100,
        );
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '10.0.0.1']);
        $next = fn (Request $r) => new Response('OK');

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers)->toHaveKey('X-RateLimit-Limit')
            ->and($headers['X-RateLimit-Limit'])->toBe('100')
            ->and($headers)->toHaveKey('X-RateLimit-Remaining')
            ->and($headers['X-RateLimit-Remaining'])->toBe('42');
    });

    it('includes retry after header on blocked response', function (): void {
        $limiter = createMockLimiter(new RateLimitResult(
            allowed: false,
            remaining: 0,
            retryAfter: 45,
        ));

        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '10.0.0.1']);
        $next = fn (Request $r) => new Response('OK');

        $response = $middleware->handle($request, $next);

        $headers = $response->headers();

        expect($headers)->toHaveKey('Retry-After')
            ->and($headers['Retry-After'])->toBe('45');
    });

    it('uses client IP as default rate limit key', function (): void {
        $capturedKey = null;

        $limiter = new class ($capturedKey) implements RateLimiterInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private ?string &$capturedKey,
            ) {}

            public function attempt(
                string $key,
                int $maxAttempts,
                int $decaySeconds,
            ): RateLimitResult {
                $this->capturedKey = $key;

                return new RateLimitResult(
                    allowed: true,
                    remaining: 59,
                );
            }

            public function tooManyAttempts(
                string $key,
                int $maxAttempts,
            ): bool {
                return false;
            }

            public function clear(
                string $key,
            ): void {}
        };

        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_X_FORWARDED_FOR' => '203.0.113.50']);
        $next = fn (Request $r) => new Response('OK');

        $middleware->handle($request, $next);

        expect($capturedKey)->toBe('203.0.113.50');
    });

    it('falls back to Remote-Addr header when X-Forwarded-For missing', function (): void {
        $capturedKey = null;

        $limiter = new class ($capturedKey) implements RateLimiterInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private ?string &$capturedKey,
            ) {}

            public function attempt(
                string $key,
                int $maxAttempts,
                int $decaySeconds,
            ): RateLimitResult {
                $this->capturedKey = $key;

                return new RateLimitResult(
                    allowed: true,
                    remaining: 59,
                );
            }

            public function tooManyAttempts(
                string $key,
                int $maxAttempts,
            ): bool {
                return false;
            }

            public function clear(
                string $key,
            ): void {}
        };

        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request(server: ['HTTP_REMOTE_ADDR' => '10.0.0.99']);
        $next = fn (Request $r) => new Response('OK');

        $middleware->handle($request, $next);

        expect($capturedKey)->toBe('10.0.0.99');
    });

    it('falls back to unknown when no IP headers present', function (): void {
        $capturedKey = null;

        $limiter = new class ($capturedKey) implements RateLimiterInterface
        {
            public function __construct(
                /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
                private ?string &$capturedKey,
            ) {}

            public function attempt(
                string $key,
                int $maxAttempts,
                int $decaySeconds,
            ): RateLimitResult {
                $this->capturedKey = $key;

                return new RateLimitResult(
                    allowed: true,
                    remaining: 59,
                );
            }

            public function tooManyAttempts(
                string $key,
                int $maxAttempts,
            ): bool {
                return false;
            }

            public function clear(
                string $key,
            ): void {}
        };

        $middleware = new RateLimitMiddleware($limiter);
        $request = new Request();
        $next = fn (Request $r) => new Response('OK');

        $middleware->handle($request, $next);

        expect($capturedKey)->toBe('unknown');
    });
});
