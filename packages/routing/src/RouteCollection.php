<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Routing\Exceptions\RouteConflictException;

class RouteCollection
{
    /** @var array<string, RouteDefinition> */
    private array $routes = [];

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

        $this->routes[$key] = $route;
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
     * @return array<int, RouteDefinition>
     */
    public function byMethod(
        string $method,
    ): array {
        return array_values(
            array_filter(
                $this->routes,
                fn (RouteDefinition $route): bool => $route->method === $method,
            ),
        );
    }

    private function buildKey(
        string $method,
        string $path,
    ): string {
        return $method . ':' . $path;
    }
}
