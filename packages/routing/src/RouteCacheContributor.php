<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Discovery\DiscoveryCacheContributorInterface;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use ReflectionException;

/**
 * Stores every discovered route in the discovery cache and rebuilds the
 * RouteCollection from it.
 *
 * Each route is stored as its RouteDefinition constructor arguments (plain
 * strings and lists, prefix already applied) in registration order.
 * RouteCollection derives match order from the routes themselves with a
 * stable sort, so a hydrated collection matches, names and lists routes
 * exactly like the live one. Controllers are referenced by name only and
 * are autoloaded when a request matches them.
 */
readonly class RouteCacheContributor implements DiscoveryCacheContributorInterface
{
    public const string KEY = 'routes';

    public function __construct(
        private RouteCollector $routeCollector,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array<ModuleManifest> $modules
     * @return array<int, array{method: string, path: string, controller: string, action: string, middleware: array<int, string>, name: ?string, withoutMiddleware: array<int, string>}>
     *
     * @throws RouteException|RouteConflictException|ReflectionException
     */
    public function compile(
        array $modules,
    ): array {
        return array_map(
            fn (RouteDefinition $route): array => [
                'method' => $route->method,
                'path' => $route->path,
                'controller' => $route->controller,
                'action' => $route->action,
                'middleware' => array_values($route->middleware),
                'name' => $route->name,
                'withoutMiddleware' => array_values($route->withoutMiddleware),
            ],
            $this->routeCollector->collect($modules)->all(),
        );
    }

    /**
     * Rebuild the route collection from a cached section.
     *
     * @param array<mixed> $section
     *
     * @throws DiscoveryCacheException|RouteException|RouteConflictException
     */
    public function hydrate(
        array $section,
    ): RouteCollection {
        $routes = new RouteCollection();

        foreach ($section as $index => $record) {
            if (!is_array($record)) {
                throw DiscoveryCacheException::malformedSection(self::KEY, "route $index must be an array");
            }

            $routes->add(new RouteDefinition(
                method: $this->stringField($record, $index, 'method'),
                path: $this->stringField($record, $index, 'path'),
                controller: $this->stringField($record, $index, 'controller'),
                action: $this->stringField($record, $index, 'action'),
                middleware: $this->stringListField($record, $index, 'middleware'),
                name: $this->nullableStringField($record, $index, 'name'),
                withoutMiddleware: $this->stringListField($record, $index, 'withoutMiddleware'),
            ));
        }

        return $routes;
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function stringField(
        array $record,
        int|string $index,
        string $field,
    ): string {
        if (
            !isset($record[$field])
            || !is_string($record[$field])
        ) {
            throw DiscoveryCacheException::malformedSection(self::KEY, "route $index.$field must be a string");
        }

        return $record[$field];
    }

    /**
     * @param array<mixed> $record
     *
     * @throws DiscoveryCacheException
     */
    private function nullableStringField(
        array $record,
        int|string $index,
        string $field,
    ): ?string {
        if (
            !array_key_exists($field, $record)
            || (
                $record[$field] !== null
                && !is_string($record[$field])
            )
        ) {
            throw DiscoveryCacheException::malformedSection(self::KEY, "route $index.$field must be a string or null");
        }

        return $record[$field];
    }

    /**
     * @param array<mixed> $record
     * @return array<int, string>
     *
     * @throws DiscoveryCacheException
     */
    private function stringListField(
        array $record,
        int|string $index,
        string $field,
    ): array {
        if (
            !isset($record[$field])
            || !is_array($record[$field])
            || !array_all($record[$field], fn (mixed $value): bool => is_string($value))
        ) {
            throw DiscoveryCacheException::malformedSection(
                self::KEY,
                "route $index.$field must be a list of strings",
            );
        }

        return array_values($record[$field]);
    }
}
