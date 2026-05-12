<?php

declare(strict_types=1);

namespace Marko\PageCache;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\RouteMatcherInterface;
use ReflectionException;
use ReflectionMethod;

readonly class CacheabilityChecker
{
    public function __construct(
        private RouteMatcherInterface $routeMatcher,
        private PageCacheConfig $config,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function isRequestCacheable(Request $request): bool
    {
        return in_array($request->method(), $this->config->cacheableMethods(), true);
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function isResponseCacheable(Response $response): bool
    {
        if (!in_array($response->statusCode(), $this->config->cacheableStatusCodes(), true)) {
            return false;
        }

        if ($this->getHeader($response, 'set-cookie') !== null) {
            return false;
        }

        $cacheControl = $this->getHeader($response, 'cache-control');

        if ($cacheControl !== null) {
            $directives = $this->parseDirectives($cacheControl);

            if (in_array('no-store', $directives, true) || in_array('private', $directives, true)) {
                return false;
            }
        }

        return true;
    }

    public function getRouteAttribute(Request $request): ?Cacheable
    {
        $matched = $this->routeMatcher->match($request->method(), $request->path());

        if ($matched === null) {
            return null;
        }

        try {
            $method = new ReflectionMethod($matched->route->controller, $matched->route->action);
            $attributes = $method->getAttributes(Cacheable::class);

            if ($attributes === []) {
                return null;
            }

            return $attributes[0]->newInstance();
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @return array<string>
     */
    private function parseDirectives(string $header): array
    {
        return array_map(
            fn (string $token): string => strtolower(trim(explode('=', $token)[0])),
            explode(',', $header),
        );
    }

    private function getHeader(
        Response $response,
        string $name,
    ): ?string {
        $name = strtolower($name);

        foreach ($response->headers() as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }
}
