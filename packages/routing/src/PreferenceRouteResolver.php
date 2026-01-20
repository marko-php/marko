<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Container\PreferenceRegistry;
use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Exceptions\RouteException;
use ReflectionClass;

class PreferenceRouteResolver
{
    public function __construct(
        private PreferenceRegistry $preferenceRegistry,
        private RouteDiscovery $routeDiscovery,
    ) {}

    /**
     * Check if a class is a registered Preference for another class.
     *
     * @param class-string $childClass
     * @param class-string $parentClass
     */
    public function isPreferenceFor(
        string $childClass,
        string $parentClass,
    ): bool {
        $preference = $this->preferenceRegistry->getPreference($parentClass);

        return $preference === $childClass;
    }

    /**
     * Resolve routes for a controller class, handling Preference inheritance.
     *
     * @param class-string $className
     * @return array<RouteDefinition>
     * @throws RouteException When a method is overridden without a route attribute
     */
    public function resolveRoutes(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $parentClass = $reflection->getParentClass();

        // If no parent, just discover routes normally
        if ($parentClass === false) {
            return $this->routeDiscovery->discoverFromClass($className);
        }

        // Get parent's routes
        $parentRoutes = $this->routeDiscovery->discoverFromClass($parentClass->getName());

        // Get child's own routes
        $childRoutes = $this->routeDiscovery->discoverFromClass($className);

        // For inherited methods (not overridden), use parent route but with child controller
        $resolvedRoutes = [];

        foreach ($parentRoutes as $parentRoute) {
            // Check if child has its own route for this action
            $childHasRoute = false;
            foreach ($childRoutes as $childRoute) {
                if ($childRoute->action === $parentRoute->action) {
                    $childHasRoute = true;
                    break;
                }
            }

            if (!$childHasRoute) {
                // Check if method is overridden and has DisableRoute
                if ($this->isMethodDisabled($reflection, $parentRoute->action)) {
                    // Route is disabled, don't include it
                    continue;
                }

                // Check if method is overridden without any route attribute
                if ($this->isMethodOverriddenWithoutRouteAttribute($reflection, $parentRoute->action)) {
                    throw RouteException::ambiguousOverride(
                        parentClass: $parentClass->getName(),
                        childClass: $className,
                        method: $parentRoute->action,
                    );
                }

                // Inherit parent route but use child controller
                $resolvedRoutes[] = new RouteDefinition(
                    method: $parentRoute->method,
                    path: $parentRoute->path,
                    controller: $className,
                    action: $parentRoute->action,
                    middleware: $parentRoute->middleware,
                );
            }
        }

        // Add child's own routes
        foreach ($childRoutes as $childRoute) {
            $resolvedRoutes[] = $childRoute;
        }

        return $resolvedRoutes;
    }

    /**
     * Check if a method has the DisableRoute attribute.
     */
    private function isMethodDisabled(
        ReflectionClass $reflection,
        string $methodName,
    ): bool {
        if (!$reflection->hasMethod($methodName)) {
            return false;
        }

        $method = $reflection->getMethod($methodName);

        // Only check if method is declared in this class (overridden)
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            return false;
        }

        return !empty($method->getAttributes(DisableRoute::class));
    }

    /**
     * Check if a method is overridden in child class without any route attribute.
     */
    private function isMethodOverriddenWithoutRouteAttribute(
        ReflectionClass $reflection,
        string $methodName,
    ): bool {
        if (!$reflection->hasMethod($methodName)) {
            return false;
        }

        $method = $reflection->getMethod($methodName);

        // Only check if method is declared in this class (overridden)
        if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
            return false;
        }

        // Method is overridden - it has no route (we already checked) and no DisableRoute
        return true;
    }
}
