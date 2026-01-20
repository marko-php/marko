<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Preference;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use ReflectionClass;
use RuntimeException;

class Application
{
    /** @var array<ModuleManifest> */
    private array $modules = [];

    private ContainerInterface $container;

    private PreferenceRegistry $preferenceRegistry;

    private PluginRegistry $pluginRegistry;

    private ObserverRegistry $observerRegistry;

    private EventDispatcherInterface $eventDispatcher;

    private ?Router $router = null;

    public function __construct(
        private string $vendorPath = '',
        private string $modulesPath = '',
        private string $appPath = '',
    ) {}

    /**
     * @throws ModuleException When a required module dependency is not found
     * @throws CircularDependencyException When modules have circular dependencies
     */
    public function boot(): void
    {
        $parser = new ManifestParser();
        $discovery = new ModuleDiscovery($parser);
        $resolver = new DependencyResolver();

        $vendorModules = $discovery->discoverInVendor($this->vendorPath);
        $customModules = $discovery->discoverInModules($this->modulesPath);
        $appModules = $discovery->discoverInApp($this->appPath);

        $allModules = array_merge($vendorModules, $customModules, $appModules);

        // Resolve dependencies and sort modules
        $this->modules = $resolver->resolve($allModules);

        // Register PSR-4 autoloaders for non-vendor modules
        $this->registerAutoloaders();

        // Initialize container and registries
        $this->preferenceRegistry = new PreferenceRegistry();
        $this->container = new Container($this->preferenceRegistry);
        $bindingRegistry = new BindingRegistry($this->container);

        // Register bindings from all modules
        foreach ($this->modules as $module) {
            $bindingRegistry->registerModule($module);
        }

        // Discover and register preferences
        $this->discoverPreferences();

        // Discover and register plugins
        $this->discoverPlugins();

        // Discover and register observers
        $this->discoverObservers();

        // Create event dispatcher and register in container
        $this->eventDispatcher = new EventDispatcher($this->container, $this->observerRegistry);
        $this->container->instance(EventDispatcherInterface::class, $this->eventDispatcher);

        // Discover and register routes (if routing package is available)
        $this->discoverRoutes();
    }

    /**
     * Register PSR-4 autoloaders for non-vendor modules.
     *
     * Vendor modules are already autoloaded via Composer.
     */
    private function registerAutoloaders(): void
    {
        foreach ($this->modules as $module) {
            // Skip vendor modules - they're already handled by Composer
            if ($module->source === 'vendor') {
                continue;
            }

            foreach ($module->autoload as $namespace => $path) {
                $basePath = $module->path . '/' . rtrim($path, '/');
                $this->registerPsr4Autoloader($namespace, $basePath);
            }
        }
    }

    /**
     * Register a PSR-4 autoloader for a namespace prefix.
     */
    private function registerPsr4Autoloader(
        string $namespace,
        string $basePath,
    ): void {
        spl_autoload_register(function (string $class) use ($namespace, $basePath): void {
            // Check if class uses the registered namespace
            if (!str_starts_with($class, $namespace)) {
                return;
            }

            // Get the relative class name
            $relativeClass = substr($class, strlen($namespace));

            // Convert namespace separators to directory separators
            $file = $basePath . '/' . str_replace('\\', '/', $relativeClass) . '.php';

            if (is_file($file)) {
                require_once $file;
            }
        });
    }

    private function discoverPreferences(): void
    {
        $preferenceDiscovery = new PreferenceDiscovery();

        foreach ($this->modules as $module) {
            $files = $preferenceDiscovery->discoverInModule($module);

            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className === null) {
                    continue;
                }

                require_once $file;

                if (!class_exists($className)) {
                    continue;
                }

                $reflection = new ReflectionClass($className);
                $attributes = $reflection->getAttributes(Preference::class);

                if (empty($attributes)) {
                    continue;
                }

                $preference = $attributes[0]->newInstance();
                $this->preferenceRegistry->register($preference->replaces, $className);
            }
        }
    }

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

    private function discoverPlugins(): void
    {
        $this->pluginRegistry = new PluginRegistry();
        $pluginDiscovery = new PluginDiscovery();

        foreach ($this->modules as $module) {
            $files = $pluginDiscovery->discoverInModule($module);

            foreach ($files as $file) {
                $className = $this->extractClassName($file);
                if ($className === null) {
                    continue;
                }

                require_once $file;

                if (!class_exists($className)) {
                    continue;
                }

                // Verify the class actually has the #[Plugin] attribute
                $reflection = new ReflectionClass($className);
                $pluginAttributes = $reflection->getAttributes(Plugin::class);

                if (empty($pluginAttributes)) {
                    continue;
                }

                $definition = $pluginDiscovery->parsePluginClass($className);
                $this->pluginRegistry->register($definition);
            }
        }
    }

    /**
     * @return array<ModuleManifest>
     */
    public function getModules(): array
    {
        return $this->modules;
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function getPluginRegistry(): PluginRegistry
    {
        return $this->pluginRegistry;
    }

    public function getObserverRegistry(): ObserverRegistry
    {
        return $this->observerRegistry;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    private function discoverObservers(): void
    {
        $this->observerRegistry = new ObserverRegistry();
        $observerDiscovery = new ObserverDiscovery();

        $observers = $observerDiscovery->discover($this->modules);

        foreach ($observers as $definition) {
            $this->observerRegistry->register($definition);
        }
    }

    public function getVendorPath(): string
    {
        return $this->vendorPath;
    }

    public function getModulesPath(): string
    {
        return $this->modulesPath;
    }

    public function getAppPath(): string
    {
        return $this->appPath;
    }

    public function getRouter(): Router
    {
        if ($this->router === null) {
            throw new RuntimeException('Router not available. Call boot() first.');
        }

        return $this->router;
    }

    private function discoverRoutes(): void
    {
        // Only bootstrap routing if the routing package is available
        if (!class_exists(RoutingBootstrapper::class)) {
            return;
        }

        $bootstrapper = new RoutingBootstrapper(
            $this->modules,
            $this->container,
            $this->preferenceRegistry,
        );

        $this->router = $bootstrapper->boot();
    }
}
