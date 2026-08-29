<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Middleware;

use JsonException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiter\ClientIpResolver;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiterInterface $limiter,
        private ClientIpResolver $clientIpResolver,
        private int $maxAttempts = 60,
        private int $decaySeconds = 60,
    ) {}

    /**
     * @throws ClientIpException|ConfigNotFoundException|JsonException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $key = $this->clientIpResolver->resolve($request);
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

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) $result->remaining(),
        ]);
    }
}
