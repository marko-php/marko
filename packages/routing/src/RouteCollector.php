<?php

declare(strict_types=1);

namespace Marko\Routing;

use Error;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\Route;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use ReflectionClass;
use ReflectionException;

/**
 * Live route discovery: scans every module's src/ directory for controllers
 * and collects their routes, honouring #[Preference] inheritance.
 *
 * Used by RoutingBootstrapper on a live boot and by RouteCacheContributor
 * when compiling the discovery cache, so both produce the same routes in
 * the same registration order.
 */
readonly class RouteCollector
{
    private RouteDiscovery $discovery;

    private PreferenceRouteResolver $resolver;

    public function __construct(
        private PreferenceRegistry $preferenceRegistry,
        private ClassFileParser $classFileParser,
    ) {
        $this->discovery = new RouteDiscovery();
        $this->resolver = new PreferenceRouteResolver($this->preferenceRegistry, $this->discovery);
    }

    /**
     * Collect the routes of every module, in module load order.
     *
     * @param array<ModuleManifest> $modules
     * @throws RouteException|RouteConflictException|ReflectionException
     */
    public function collect(
        array $modules,
    ): RouteCollection {
        $routes = new RouteCollection();

        /** @var array<string, bool> $processedControllers */
        $processedControllers = [];

        foreach ($modules as $module) {
            foreach ($this->findControllerClasses($module) as $className) {
                // Skip if this controller's parent has a Preference that replaces it
                // (we'll process the Preference class instead)
                if ($this->preferenceRegistry->getPreference($className) !== null) {
                    continue;
                }

                // Skip if already processed (e.g., as a Preference)
                if (isset($processedControllers[$className])) {
                    continue;
                }

                $processedControllers[$className] = true;

                // Resolve routes considering Preference inheritance
                foreach ($this->resolver->resolveRoutes($className) as $route) {
                    $routes->add($route);
                }
            }
        }

        return $routes;
    }

    /**
     * Find controller classes in a module's src directory.
     *
     * @return array<string>
     * @throws ReflectionException
     */
    private function findControllerClasses(
        ModuleManifest $module,
    ): array {
        $srcPath = $module->path . '/src';
        if (!is_dir($srcPath)) {
            return [];
        }

        $classes = [];

        foreach ($this->classFileParser->findPhpFiles($srcPath) as $file) {
            $filePath = $file->getPathname();
            $className = $this->classFileParser->extractClassName($filePath);

            if ($className === null) {
                continue;
            }

            // Ensure the class file is loaded
            if (!$this->classFileParser->loadClass($filePath, $className)) {
                continue;
            }

            // Check if the class has any route attributes (including from parent)
            // or if it's a Preference that replaces a controller with routes
            if ($this->isRoutableController($className)) {
                $classes[] = $className;
            }
        }

        return $classes;
    }

    /**
     * A class is routable if it has route attributes on its methods, or it is a
     * Preference that extends a class with route attributes.
     *
     * @throws ReflectionException
     */
    private function isRoutableController(
        string $className,
    ): bool {
        $reflection = new ReflectionClass($className);

        return $this->hasRouteAttributes($reflection)
            || $this->isPreferenceForController($reflection);
    }

    /**
     * Check if a class has any route attributes on its methods, declared or inherited.
     */
    private function hasRouteAttributes(
        ReflectionClass $reflection,
    ): bool {
        foreach ($reflection->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                try {
                    $instance = $attribute->newInstance();
                } catch (Error) {
                    continue;
                }
                if ($instance instanceof Route) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a class is a Preference that extends a controller with routes.
     *
     * @throws ReflectionException
     */
    private function isPreferenceForController(
        ReflectionClass $reflection,
    ): bool {
        $preferenceAttributes = $reflection->getAttributes(Preference::class);
        if (empty($preferenceAttributes)) {
            return false;
        }

        $preference = $preferenceAttributes[0]->newInstance();
        $replacedClass = $preference->replaces;

        // Check if the replaced class has route attributes
        if (!class_exists($replacedClass)) {
            return false;
        }

        return $this->hasRouteAttributes(new ReflectionClass($replacedClass));
    }
}
