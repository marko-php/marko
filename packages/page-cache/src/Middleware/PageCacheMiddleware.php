<?php

declare(strict_types=1);

namespace Marko\PageCache\Middleware;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Container\ContainerInterface;
use Marko\PageCache\CacheabilityChecker;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Contracts\CacheTagProviderInterface;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

readonly class PageCacheMiddleware implements MiddlewareInterface
{
    public function __construct(
        private PageCacheInterface $pageCache,
        private CacheabilityChecker $cacheabilityChecker,
        private ContainerInterface $container,
    ) {}

    /**
     * Handle the incoming request, serving from or storing to the page cache.
     *
     * @param Request $request The incoming HTTP request
     * @param callable(Request): Response $next The next middleware or handler in the pipeline
     * @return Response The HTTP response
     *
     * @throws ConfigNotFoundException|PageCacheException
     */
    public function handle(Request $request, callable $next): Response
    {
        if (!$this->cacheabilityChecker->isRequestCacheable($request)) {
            return $next($request);
        }

        $cacheable = $this->cacheabilityChecker->getRouteAttribute($request);

        if ($cacheable === null) {
            return $next($request);
        }

        $hit = $this->pageCache->lookup($request);

        if ($hit !== null) {
            return $hit;
        }

        $response = $next($request);

        if (!$this->cacheabilityChecker->isResponseCacheable($response)) {
            return $response;
        }

        $staticTags = $cacheable->tags;
        $dynamicTags = [];

        if ($cacheable->provider !== null) {
            $provider = $this->container->get($cacheable->provider);

            if (!$provider instanceof CacheTagProviderInterface) {
                throw PageCacheException::invalidTagProvider($cacheable->provider);
            }

            $dynamicTags = $provider->tags($request, $cacheable);
        }

        $finalTags = array_values(array_unique([...$staticTags, ...$dynamicTags]));

        return $this->pageCache->store(
            $request,
            $response,
            new CachePolicy(ttl: $cacheable->ttl, tags: $finalTags),
        );
    }
}
