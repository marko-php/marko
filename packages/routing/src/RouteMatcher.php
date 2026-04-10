<?php

declare(strict_types=1);

namespace Marko\Routing;

readonly class RouteMatcher implements RouteMatcherInterface
{
    public function __construct(
        private RouteCollection $routes,
    ) {}

    public function match(
        string $method,
        string $path,
    ): ?MatchedRoute {
        $normalizedPath = $this->normalizePath($path);

        foreach ($this->routes->byMethod($method) as $route) {
            if (preg_match($route->regex, $normalizedPath, $matches)) {
                $parameters = $this->extractParameters($route, $matches);

                return new MatchedRoute(
                    route: $route,
                    parameters: $parameters,
                );
            }
        }

        return null;
    }

    private function normalizePath(
        string $path,
    ): string {
        // Remove trailing slash, except for root path
        if ($path !== '/' && str_ends_with($path, '/')) {
            return rtrim($path, '/');
        }

        return $path;
    }

    /**
     * @param array<int|string, string> $matches
     * @return array<string, string>
     */
    private function extractParameters(
        RouteDefinition $route,
        array $matches,
    ): array {
        $parameters = [];

        foreach ($route->parameters as $name) {
            if (isset($matches[$name])) {
                $parameters[$name] = $matches[$name];
            }
        }

        return $parameters;
    }
}
