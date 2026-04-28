<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Middleware;

use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimiterInterface $limiter,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
    ) {}

    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $key = $this->resolveKey($request);
        $result = $this->limiter->attempt($key, $this->maxAttempts, $this->decaySeconds);

        if (!$result->allowed()) {
            return new Response(
                body: json_encode(['message' => 'Too Many Requests'], JSON_THROW_ON_ERROR),
                statusCode: 429,
                headers: array_merge(
                    ['Content-Type' => 'application/json'],
                    $result->retryAfter() !== null
                        ? ['Retry-After' => (string) $result->retryAfter()]
                        : [],
                ),
            );
        }

        /** @var Response $response */
        $response = $next($request);

        return new Response(
            body: $response->body(),
            statusCode: $response->statusCode(),
            headers: array_merge($response->headers(), [
                'X-RateLimit-Limit' => (string) $this->maxAttempts,
                'X-RateLimit-Remaining' => (string) $result->remaining(),
            ]),
        );
    }

    private function resolveKey(
        Request $request,
    ): string {
        return $request->header('X-Forwarded-For')
            ?? $request->header('Remote-Addr')
            ?? 'unknown';
    }
}
