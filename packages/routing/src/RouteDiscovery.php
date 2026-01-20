<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Route;
use ReflectionClass;
use ReflectionMethod;

class RouteDiscovery
{
    /**
     * Discover routes in a module's src directory.
     *
     * @return array<RouteDefinition>
     */
    public function discoverInModule(
        ModuleManifest $manifest,
    ): array {
        return [];
    }

    /**
     * Discover routes from a specific class.
     *
     * @param class-string $className
     * @return array<RouteDefinition>
     */
    public function discoverFromClass(
        string $className,
    ): array {
        $routes = [];
        $reflection = new ReflectionClass($className);
        $classMiddleware = $this->getClassMiddleware($reflection);

        foreach ($reflection->getMethods() as $method) {
            if ($this->isRouteDisabled($method)) {
                continue;
            }

            foreach ($method->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof Route) {
                    $methodMiddleware = $this->getMethodMiddleware($method);
                    $routes[] = new RouteDefinition(
                        method: $instance->getMethod(),
                        path: $instance->path,
                        controller: $className,
                        action: $method->getName(),
                        middleware: array_merge($classMiddleware, $methodMiddleware),
                    );
                }
            }
        }

        return $routes;
    }

    /**
     * Get middleware defined at the class level.
     *
     * @return array<string>
     */
    private function getClassMiddleware(
        ReflectionClass $reflection,
    ): array {
        $middlewareAttributes = $reflection->getAttributes(Middleware::class);
        if (empty($middlewareAttributes)) {
            return [];
        }

        $middleware = $middlewareAttributes[0]->newInstance();

        return $middleware->middleware;
    }

    /**
     * Get middleware defined at the method level.
     *
     * @return array<string>
     */
    private function getMethodMiddleware(
        ReflectionMethod $method,
    ): array {
        $middlewareAttributes = $method->getAttributes(Middleware::class);
        if (empty($middlewareAttributes)) {
            return [];
        }

        $middleware = $middlewareAttributes[0]->newInstance();

        return $middleware->middleware;
    }

    /**
     * Check if a method has the DisableRoute attribute.
     */
    private function isRouteDisabled(
        ReflectionMethod $method,
    ): bool {
        return !empty($method->getAttributes(DisableRoute::class));
    }
}
