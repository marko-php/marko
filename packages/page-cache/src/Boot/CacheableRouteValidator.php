<?php

declare(strict_types=1);

namespace Marko\PageCache\Boot;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\PageCache\Middleware\PageCacheMiddleware;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use ReflectionException;
use ReflectionMethod;

/**
 * Boot-time validator that rejects #[Cacheable] routes guarded by authentication middleware.
 *
 * PageCacheMiddleware is global middleware, so a cache hit is returned before any route
 * middleware runs. An auth-guarded cacheable route would serve its cached page to anyone.
 */
readonly class CacheableRouteValidator
{
    public function __construct(
        private PageCacheConfig $config,
    ) {}

    /**
     * @throws ConfigNotFoundException|PageCacheException When a cacheable route carries auth middleware
     */
    public function validate(
        RouteCollection $routes,
    ): void {
        $patterns = $this->config->authMiddlewarePatterns();

        if ($patterns === []) {
            return;
        }

        $cacheableMethods = $this->config->cacheableMethods();

        foreach ($routes->all() as $route) {
            if (!in_array(strtoupper($route->method), $cacheableMethods, true)) {
                continue;
            }

            if (in_array(PageCacheMiddleware::class, $route->withoutMiddleware, true)) {
                continue;
            }

            if (!$this->isCacheable($route)) {
                continue;
            }

            $activeMiddleware = array_diff($route->middleware, $route->withoutMiddleware);

            $authMiddleware = array_find(
                $activeMiddleware,
                fn (string $middleware): bool => $this->matchesAuthPattern($middleware, $patterns),
            );

            if ($authMiddleware !== null) {
                throw PageCacheException::cacheableRouteWithAuthMiddleware(
                    $route->controller,
                    $route->action,
                    $route->path,
                    $authMiddleware,
                );
            }
        }
    }

    private function isCacheable(
        RouteDefinition $route,
    ): bool {
        try {
            $method = new ReflectionMethod($route->controller, $route->action);
        } catch (ReflectionException) {
            return false;
        }

        return $method->getAttributes(Cacheable::class) !== [];
    }

    /**
     * @param array<string> $patterns
     */
    private function matchesAuthPattern(
        string $middleware,
        array $patterns,
    ): bool {
        $position = strrpos($middleware, '\\');
        $shortName = $position === false ? $middleware : substr($middleware, $position + 1);

        return array_any(
            $patterns,
            fn (string $pattern): bool => fnmatch($pattern, $shortName, FNM_CASEFOLD),
        );
    }
}
