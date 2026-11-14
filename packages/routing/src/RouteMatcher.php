<?php

declare(strict_types=1);

namespace Marko\Routing;

/**
 * Matches a request method and path against the route collection.
 *
 * Static routes are found by exact lookup; dynamic routes are tried in the
 * collection's precedence order (see RouteCollection); a route whose decoded
 * parameter values fail RouteDefinition::acceptsValue() is skipped. A HEAD request with no
 * matching HEAD route falls back to the GET route for the same path.
 *
 * Dynamic hits are memoized so middleware and the router can call match() for
 * the same request without repeating regex work. The memo holds hits only and
 * is cleared when it reaches MAX_MEMO_ENTRIES, so a long-running worker cannot
 * grow it without bound from unique URLs.
 */
class RouteMatcher implements RouteMatcherInterface
{
    public const int MAX_MEMO_ENTRIES = 1000;

    /** @var array<string, MatchedRoute> */
    private array $memo = [];

    public function __construct(
        private readonly RouteCollection $routes,
    ) {}

    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute {
        $normalizedPath = $this->normalizePath($path);

        $matched = $this->matchMethod($method, $normalizedPath);

        if (
            $matched === null
            && $method === 'HEAD'
        ) {
            $matched = $this->matchMethod('GET', $normalizedPath);
        }

        return $matched;
    }

    /**
     * @return array<int, string>
     */
    public function allowedMethods(
        string $path,
    ): array {
        $normalizedPath = $this->normalizePath($path);

        $allowed = array_values(array_filter(
            $this->routes->methods(),
            fn (string $method): bool => $this->matchMethod($method, $normalizedPath) !== null,
        ));

        if ($allowed === []) {
            return [];
        }

        if (in_array('GET', $allowed, true)) {
            $allowed[] = 'HEAD';
        }

        $allowed[] = 'OPTIONS';

        return $this->routes->sortMethods(array_values(array_unique($allowed)));
    }

    private function matchMethod(
        string $method,
        string $normalizedPath,
    ): ?MatchedRoute {
        $static = $this->routes->staticRoute($method, $normalizedPath);

        if ($static !== null) {
            return new MatchedRoute(route: $static);
        }

        $memoKey = $method . ':' . $normalizedPath;

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        foreach ($this->routes->byMethod($method) as $route) {
            if ($route->isStatic) {
                continue;
            }

            if (!preg_match($route->regex, $normalizedPath, $matches)) {
                continue;
            }

            $parameters = $this->extractParameters($route, $matches);

            if ($parameters === null) {
                continue;
            }

            return $this->remember($memoKey, new MatchedRoute(
                route: $route,
                parameters: $parameters,
            ));
        }

        return null;
    }

    private function remember(
        string $memoKey,
        MatchedRoute $matched,
    ): MatchedRoute {
        if (count($this->memo) >= self::MAX_MEMO_ENTRIES) {
            $this->memo = [];
        }

        return $this->memo[$memoKey] = $matched;
    }

    private function normalizePath(
        string $path,
    ): string {
        // Remove trailing slash, except for root path
        if (
            $path !== '/'
            && str_ends_with($path, '/')
        ) {
            return rtrim($path, '/');
        }

        return $path;
    }

    /**
     * Decode the captured values, or return null when a decoded value is
     * unsafe (an encoded `/`, a `.`/`..` segment or a NUL byte) so the route
     * does not match.
     *
     * @param array<int|string, string> $matches
     * @return array<string, string>|null
     */
    private function extractParameters(
        RouteDefinition $route,
        array $matches,
    ): ?array {
        $parameters = [];

        foreach ($route->parameters as $name) {
            if (!isset($matches[$name])) {
                continue;
            }

            $value = rawurldecode($matches[$name]);

            if (!$route->acceptsValue($name, $value)) {
                return null;
            }

            $parameters[$name] = $value;
        }

        return $parameters;
    }
}
