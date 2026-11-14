<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Middleware;

use JsonException;
use Marko\Cache\Exceptions\InvalidKeyException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\RateLimiter\Attributes\RateLimit;
use Marko\RateLimiter\Config\RateLimiterConfig;
use Marko\RateLimiter\Contracts\RateLimiterInterface;
use Marko\RateLimiter\Contracts\RateLimitKeyResolverInterface;
use Marko\RateLimiter\Exceptions\ClientIpException;
use Marko\RateLimiter\Exceptions\RateLimitException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Limits requests per route and client.
 *
 * Limits come from a #[RateLimit] attribute on the controller action (which
 * wins) or the controller class, and fall back to the ratelimiter config
 * defaults. Each route gets its own counter, keyed by the attribute's name or
 * by controller::action, combined with the client identity from the bound
 * RateLimitKeyResolverInterface.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private const string UNROUTED_BUCKET = 'global';

    /**
     * Resolved #[RateLimit] per controller::action (null when none is declared).
     *
     * @var array<string, ?RateLimit>
     */
    private array $rateLimits = [];

    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
        private readonly RateLimitKeyResolverInterface $rateLimitKeyResolver,
        private readonly RateLimiterConfig $rateLimiterConfig,
    ) {}

    /**
     * @throws ClientIpException|ConfigNotFoundException|InvalidKeyException|JsonException|RateLimitException|ReflectionException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $controller = $request->controller();
        $action = $request->action();
        $route = ($controller !== null && $action !== null)
            ? "$controller::$action"
            : null;
        $rateLimit = $route !== null ? $this->rateLimitFor($route, $controller, $action) : null;

        $maxAttempts = $rateLimit?->maxAttempts ?? $this->rateLimiterConfig->defaultMaxAttempts();
        $decaySeconds = $rateLimit?->decaySeconds ?? $this->rateLimiterConfig->defaultDecaySeconds();
        $bucket = $rateLimit?->name ?? $route ?? self::UNROUTED_BUCKET;

        $key = $bucket . '|' . $this->rateLimitKeyResolver->resolve($request);
        $result = $this->rateLimiter->attempt($key, $maxAttempts, $decaySeconds);

        if (!$result->allowed()) {
            $headers = [
                'X-RateLimit-Limit' => (string) $maxAttempts,
                'X-RateLimit-Remaining' => '0',
            ];

            if ($result->retryAfter() !== null) {
                $headers['Retry-After'] = (string) $result->retryAfter();
            }

            return Response::json(['message' => 'Too Many Requests'], 429)->withHeaders($headers);
        }

        /** @var Response $response */
        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => (string) $maxAttempts,
            'X-RateLimit-Remaining' => (string) $result->remaining(),
        ]);
    }

    /**
     * @throws RateLimitException|ReflectionException
     */
    private function rateLimitFor(
        string $route,
        string $controller,
        string $action,
    ): ?RateLimit {
        if (!array_key_exists($route, $this->rateLimits)) {
            $methodAttributes = new ReflectionMethod($controller, $action)->getAttributes(RateLimit::class);
            $classAttributes = new ReflectionClass($controller)->getAttributes(RateLimit::class);
            $attribute = $methodAttributes[0] ?? $classAttributes[0] ?? null;

            $this->rateLimits[$route] = $attribute?->newInstance();
        }

        return $this->rateLimits[$route];
    }
}
