<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Preference;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use Psr\Container\ContainerExceptionInterface;
use ReflectionClass;
use RuntimeException;

class Application
{
    /** @var array<ModuleManifest> */
    public private(set) array $modules = [];

    public private(set) ContainerInterface $container;

    public private(set) PreferenceRegistry $preferenceRegistry;

    public private(set) PluginRegistry $pluginRegistry;

    public private(set) ObserverRegistry $observerRegistry;

    public private(set) EventDispatcherInterface $eventDispatcher;

    public private(set) CommandRegistry $commandRegistry;

    public private(set) CommandRunner $commandRunner;

    private ?Router $_router = null;

    public Router $router {
        get => $this->_router ?? throw new RuntimeException('Router not available. Call boot() first.');
    }

    private ClassFileParser $classFileParser;

    public function __construct(
        public private(set) readonly string $vendorPath = '',
        public private(set) readonly string $modulesPath = '',
        public private(set) readonly string $appPath = '',
    ) {}

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException
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
        $this->classFileParser = new ClassFileParser();
        $this->preferenceRegistry = new PreferenceRegistry();
        $this->container = new Container($this->preferenceRegistry);
        $bindingRegistry = new BindingRegistry($this->container);

        // Register ProjectPaths for dependency injection (base path derived from vendor path)
        $basePath = dirname($this->vendorPath);
        $this->container->instance(ProjectPaths::class, new ProjectPaths($basePath));

        // Register bindings from all modules
        foreach ($this->modules as $module) {
            $bindingRegistry->registerModule($module);
        }

        // Call module boot callbacks (e.g., error handler registration)
        foreach ($this->modules as $module) {
            if ($module->boot !== null) {
                ($module->boot)($this->container);
            }
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

        // Create module repository and register in container
        $moduleRepository = new ModuleRepository($this->modules);
        $this->container->instance(ModuleRepositoryInterface::class, $moduleRepository);

        // Discover and register commands
        $this->discoverCommands();

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
        spl_autoload_register(function (
            string $class,
        ) use ($namespace, $basePath): void {
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
                $className = $this->classFileParser->extractClassName($file);
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

    /**
     * @throws PluginException
     */
    private function discoverPlugins(): void
    {
        $this->pluginRegistry = new PluginRegistry();
        $pluginDiscovery = new PluginDiscovery();

        foreach ($this->modules as $module) {
            $files = $pluginDiscovery->discoverInModule($module);

            foreach ($files as $file) {
                $className = $this->classFileParser->extractClassName($file);
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
     * @throws ContainerExceptionInterface|EventException
     */
    private function discoverObservers(): void
    {
        $this->observerRegistry = new ObserverRegistry();
        $observerDiscovery = $this->container->get(ObserverDiscovery::class);

        $observers = $observerDiscovery->discover($this->modules);

        foreach ($observers as $definition) {
            $this->observerRegistry->register($definition);
        }
    }

    /**
     * @throws CommandException
     */
    private function discoverCommands(): void
    {
        $this->commandRegistry = new CommandRegistry();
        $commandDiscovery = new CommandDiscovery($this->classFileParser);

        $commands = $commandDiscovery->discover($this->modules);

        foreach ($commands as $definition) {
            $this->commandRegistry->register($definition);
        }

        // Bind registry in container so commands can inject it
        $this->container->instance(CommandRegistry::class, $this->commandRegistry);

        $this->commandRunner = new CommandRunner($this->container, $this->commandRegistry);
    }

    /**
     * @throws RouteException|RouteConflictException
     */
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
            new ClassFileParser(),
        );

        $this->_router = $bootstrapper->boot();
    }
}
