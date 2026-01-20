<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Attributes\Preference;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Attributes\Route;
use ReflectionClass;

class RoutingBootstrapper
{
    private RouteCollection $routes;

    private RouteDiscovery $discovery;

    private PreferenceRouteResolver $resolver;

    /**
     * @param array<ModuleManifest> $modules
     */
    public function __construct(
        private array $modules,
        private ContainerInterface $container,
        private PreferenceRegistry $preferenceRegistry,
    ) {
        $this->routes = new RouteCollection();
        $this->discovery = new RouteDiscovery();
        $this->resolver = new PreferenceRouteResolver(
            $this->preferenceRegistry,
            $this->discovery,
        );
    }

    /**
     * Bootstrap the routing system: discover routes and register the Router in the container.
     */
    public function boot(): Router
    {
        // Discover routes from all modules
        $this->discoverRoutes();

        // Register RouteCollection as singleton instance
        $this->container->instance(RouteCollection::class, $this->routes);

        // Create and register Router
        $router = new Router($this->routes, $this->container);
        $this->container->instance(Router::class, $router);

        return $router;
    }

    /**
     * Discover routes from all loaded modules.
     */
    private function discoverRoutes(): void
    {
        /** @var array<string, bool> $processedControllers */
        $processedControllers = [];

        foreach ($this->modules as $module) {
            $controllerClasses = $this->findControllerClasses($module);

            foreach ($controllerClasses as $className) {
                // Skip if this controller's parent has a Preference that replaces it
                // (we'll process the Preference class instead)
                if ($this->isReplacedByPreference($className)) {
                    continue;
                }

                // Skip if already processed (e.g., as a Preference)
                if (isset($processedControllers[$className])) {
                    continue;
                }

                $processedControllers[$className] = true;

                // Resolve routes considering Preference inheritance
                $routes = $this->resolver->resolveRoutes($className);

                foreach ($routes as $route) {
                    $this->routes->add($route);
                }
            }
        }
    }

    /**
     * Find controller classes in a module's src directory.
     *
     * @return array<string>
     */
    private function findControllerClasses(ModuleManifest $module): array
    {
        $srcPath = $module->path . '/src';
        if (!is_dir($srcPath)) {
            return [];
        }

        $classes = [];
        $files = $this->findPhpFiles($srcPath);

        foreach ($files as $file) {
            $className = $this->extractClassName($file);
            if ($className === null) {
                continue;
            }

            // Ensure the class file is loaded
            require_once $file;

            if (!class_exists($className)) {
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
     * Find all PHP files in a directory recursively.
     *
     * @return array<string>
     */
    private function findPhpFiles(string $directory): array
    {
        $files = [];
        $items = scandir($directory);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->findPhpFiles($path));
            } elseif (str_ends_with($item, '.php')) {
                $files[] = $path;
            }
        }

        return $files;
    }

    /**
     * Extract the fully qualified class name from a PHP file.
     */
    private function extractClassName(string $filePath): ?string
    {
        $contents = file_get_contents($filePath);

        if ($contents === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $contents, $matches)) {
            $class = $matches[1];
        }

        if ($class === null) {
            return null;
        }

        return $namespace !== null ? $namespace . '\\' . $class : $class;
    }

    /**
     * Check if a class is a routable controller.
     *
     * A class is routable if:
     * 1. It has route attributes on its own methods, OR
     * 2. It's a Preference that extends a class with route attributes
     */
    private function isRoutableController(string $className): bool
    {
        $reflection = new ReflectionClass($className);

        // Check if this class itself has route attributes
        if ($this->hasRouteAttributes($reflection)) {
            return true;
        }

        // Check if this is a Preference that extends a controller with routes
        if ($this->isPreferenceForController($reflection)) {
            return true;
        }

        return false;
    }

    /**
     * Check if a class has any route attributes on its own methods (not inherited).
     */
    private function hasRouteAttributes(ReflectionClass $reflection): bool
    {
        foreach ($reflection->getMethods() as $method) {
            // Only check methods declared in this class, not inherited
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                // But also check if an inherited method has route attributes
                foreach ($method->getAttributes() as $attribute) {
                    $instance = $attribute->newInstance();
                    if ($instance instanceof Route) {
                        return true;
                    }
                }
                continue;
            }

            foreach ($method->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();
                if ($instance instanceof Route) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if a class is a Preference that extends a controller with routes.
     */
    private function isPreferenceForController(ReflectionClass $reflection): bool
    {
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

        $replacedReflection = new ReflectionClass($replacedClass);

        return $this->hasRouteAttributes($replacedReflection);
    }

    /**
     * Check if a class is replaced by a Preference.
     */
    private function isReplacedByPreference(string $className): bool
    {
        $preference = $this->preferenceRegistry->getPreference($className);

        return $preference !== null;
    }
}
