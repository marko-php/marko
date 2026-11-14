<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Routing\Exceptions\RouteConflictException;

/**
 * Holds every registered route and defines the order in which they are matched.
 *
 * Match order (per HTTP method) does not depend on registration order:
 *
 * 1. Static paths (no parameters) first — matched by exact lookup.
 * 2. Catch-all paths (`{path*}`) after every other dynamic path.
 * 3. Dynamic paths by descending number of static segments.
 * 4. Then by descending number of constrained parameters (`{id:\d+}`).
 * 5. Then by descending length of the static prefix before the first parameter.
 * 6. Ties keep registration order.
 */
class RouteCollection
{
    public const array METHOD_ORDER = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /** @var array<string, RouteDefinition> */
    private array $routes = [];

    /** @var array<string, RouteDefinition> Routes keyed by name */
    private array $names = [];

    /** @var array<string, array<int, RouteDefinition>> Sorted routes per method, built lazily */
    private array $sorted = [];

    /**
     * @throws RouteConflictException
     */
    public function add(
        RouteDefinition $route,
    ): void {
        $key = $this->buildKey($route->method, $route->path);

        if (isset($this->routes[$key])) {
            $existing = $this->routes[$key];
            throw RouteConflictException::duplicateRoute(
                path: $route->path,
                method: $route->method,
                existingController: $existing->controller,
                existingMethod: $existing->action,
                newController: $route->controller,
                newMethod: $route->action,
            );
        }

        if (
            $route->name !== null
            && isset($this->names[$route->name])
        ) {
            $existing = $this->names[$route->name];
            throw RouteConflictException::duplicateName(
                name: $route->name,
                existingController: $existing->controller,
                existingMethod: $existing->action,
                newController: $route->controller,
                newMethod: $route->action,
            );
        }

        $this->routes[$key] = $route;

        if ($route->name !== null) {
            $this->names[$route->name] = $route;
        }

        unset($this->sorted[$route->method]);
    }

    /**
     * The route registered under a name, or null when no route has it.
     */
    public function named(
        string $name,
    ): ?RouteDefinition {
        return $this->names[$name] ?? null;
    }

    /**
     * Every route name, in registration order.
     *
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->names);
    }

    public function has(
        string $method,
        string $path,
    ): bool {
        $key = $this->buildKey($method, $path);

        return isset($this->routes[$key]);
    }

    public function get(
        string $method,
        string $path,
    ): ?RouteDefinition {
        $key = $this->buildKey($method, $path);

        return $this->routes[$key] ?? null;
    }

    /**
     * Exact-path lookup for a route without parameters.
     */
    public function staticRoute(
        string $method,
        string $path,
    ): ?RouteDefinition {
        $route = $this->get($method, $path);

        return ($route !== null && $route->isStatic)
            ? $route
            : null;
    }

    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * @return array<int, RouteDefinition>
     */
    public function all(): array
    {
        return array_values($this->routes);
    }

    /**
     * Distinct HTTP methods that have at least one route, in registration order.
     *
     * @return array<int, string>
     */
    public function methods(): array
    {
        return array_values(array_unique(array_map(
            fn (RouteDefinition $route): string => $route->method,
            array_values($this->routes),
        )));
    }

    /**
     * Every route, grouped by method (in canonical method order) and in
     * effective match order within each method.
     *
     * @return array<int, RouteDefinition>
     */
    public function inMatchOrder(): array
    {
        return array_merge(...array_map(
            fn (string $method): array => $this->byMethod($method),
            $this->sortMethods($this->methods()),
        ));
    }

    /**
     * Sort HTTP methods canonically: GET, HEAD, POST, PUT, PATCH, DELETE,
     * OPTIONS, then any others alphabetically.
     *
     * @param array<int, string> $methods
     * @return array<int, string>
     */
    public function sortMethods(
        array $methods,
    ): array {
        $rank = array_flip(self::METHOD_ORDER);

        usort(
            $methods,
            fn (string $a, string $b): int => [$rank[$a] ?? PHP_INT_MAX, $a] <=> [$rank[$b] ?? PHP_INT_MAX, $b],
        );

        return $methods;
    }

    /**
     * Routes for one HTTP method, in effective match order.
     *
     * @return array<int, RouteDefinition>
     */
    public function byMethod(
        string $method,
    ): array {
        return $this->sorted[$method] ??= $this->sortByPrecedence(
            array_values(
                array_filter(
                    $this->routes,
                    fn (RouteDefinition $route): bool => $route->method === $method,
                ),
            ),
        );
    }

    /**
     * @param array<int, RouteDefinition> $routes
     * @return array<int, RouteDefinition>
     */
    private function sortByPrecedence(
        array $routes,
    ): array {
        // usort is stable since PHP 8.0, so equal routes keep registration order.
        usort($routes, fn (RouteDefinition $a, RouteDefinition $b): int => [
            $b->isStatic,
            $b->catchAll === null,
            $b->staticSegmentCount,
            count($b->constraints),
            $b->staticPrefixLength,
        ] <=> [
            $a->isStatic,
            $a->catchAll === null,
            $a->staticSegmentCount,
            count($a->constraints),
            $a->staticPrefixLength,
        ]);

        return $routes;
    }

    private function buildKey(
        string $method,
        string $path,
    ): string {
        return $method . ':' . $path;
    }
}
