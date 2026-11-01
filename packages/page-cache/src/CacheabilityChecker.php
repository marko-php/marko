<?php

declare(strict_types=1);

namespace Marko\PageCache;

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\PageCache\Attributes\Cacheable;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\PageCache\Exceptions\PageCacheException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponseInterface;
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
     * Whether the request may be served from, or stored in, the page cache.
     *
     * Requests carrying credentials (an Authorization header, the session cookie or any configured
     * bypass cookie) are never cacheable: the page cache runs before route middleware, so a cached
     * page would otherwise be served without auth and one user's page could be stored for everyone.
     *
     * @throws ConfigNotFoundException
     */
    public function isRequestCacheable(Request $request): bool
    {
        if (!in_array($request->method(), $this->config->cacheableMethods(), true)) {
            return false;
        }

        if ($this->hasCredentials($request) || $this->hasBypassCookie($request)) {
            return false;
        }

        return $this->isTrustedHost($request);
    }

    /**
     * @throws ConfigNotFoundException
     */
    public function isResponseCacheable(Response $response): bool
    {
        // A streamed body is written by sendBody(), never held in body(): caching it would store ''.
        if ($response instanceof StreamedResponseInterface) {
            return false;
        }

        if (!in_array($response->statusCode(), $this->config->cacheableStatusCodes(), true)) {
            return false;
        }

        if ($response->cookies() !== []) {
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

    /**
     * @throws PageCacheException
     */
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

    private function hasCredentials(Request $request): bool
    {
        return $request->header('Authorization') !== null
            || $request->server('REDIRECT_HTTP_AUTHORIZATION') !== null
            || $request->server('PHP_AUTH_USER') !== null
            || $request->server('PHP_AUTH_DIGEST') !== null;
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function hasBypassCookie(Request $request): bool
    {
        $cookieNames = array_map(strval(...), array_keys($request->cookie()));

        if ($cookieNames === []) {
            return false;
        }

        $patterns = $this->config->bypassCookies();

        return array_any(
            $cookieNames,
            fn (string $name): bool => array_any(
                $patterns,
                fn (string $pattern): bool => fnmatch($pattern, $name),
            ),
        );
    }

    /**
     * @throws ConfigNotFoundException
     */
    private function isTrustedHost(Request $request): bool
    {
        $trustedHosts = $this->config->trustedHosts();

        if ($trustedHosts === []) {
            return true;
        }

        $host = CacheKey::hostnameFromRequest($request);

        return $host !== '' && array_any(
            $trustedHosts,
            fn (string $pattern): bool => fnmatch($pattern, $host),
        );
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

        return array_find(
            $response->headers(),
            fn (string $value, string $key): bool => strtolower($key) === $name,
        );
    }
}
