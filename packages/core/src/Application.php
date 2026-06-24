<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Core\Module\DependencyResolver;
use Marko\Core\Module\GlobalMiddlewareResolver;
use Marko\Core\Module\ManifestParser;
use Marko\Core\Module\ModuleAutoloader;
use Marko\Core\Module\ModuleDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Core\Plugin\InterceptorClassGenerator;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;
use Marko\Core\Plugin\PluginInterceptor;
use Marko\Core\Plugin\PluginRegistry;
use Marko\Env\EnvLoader;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\Router;
use Marko\Routing\RoutingBootstrapper;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
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

    /** @var ?Router */
    private ?object $_router = null;

    /** @var Router */
    public object $router {
        get => $this->_router ?? throw new RuntimeException(
            'Router not available. Install marko/routing: composer require marko/routing',
        );
    }

    private ClassFileParser $classFileParser;

    public function __construct(
        public private(set) readonly string $vendorPath = '',
        public private(set) readonly string $modulesPath = '',
        public private(set) readonly string $appPath = '',
    ) {}

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    public static function boot(string $basePath): self
    {
        if (!is_dir($basePath)) {
            throw new RuntimeException("Base path does not exist: $basePath");
        }

        $app = new self(
            vendorPath: $basePath . '/vendor',
            modulesPath: $basePath . '/modules',
            appPath: $basePath . '/app',
        );

        $app->initialize();

        return $app;
    }

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|DiscoveryCacheException
     */
    public function initialize(): void
    {
        // Load environment variables if marko/env is installed
        if (class_exists(EnvLoader::class)) {
            $basePath = dirname($this->vendorPath);
            (new EnvLoader())->load($basePath);
        }

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
        $this->pluginRegistry = new PluginRegistry();
        $this->container = new Container($this->preferenceRegistry);
        $this->container->instance(ContainerInterface::class, $this->container);
        $interceptor = new PluginInterceptor($this->container, $this->pluginRegistry, new InterceptorClassGenerator());
        $this->container->setPluginInterceptor($interceptor);
        $this->container->instance(PluginInterceptor::class, $interceptor);
        $this->container->instance(PluginRegistry::class, $this->pluginRegistry);
        $bindingRegistry = new BindingRegistry($this->container);

        // Register ProjectPaths for dependency injection (base path derived from vendor path)
        $basePath = dirname($this->vendorPath);
        $projectPaths = new ProjectPaths($basePath);
        $this->container->instance(ProjectPaths::class, $projectPaths);

        // Register bindings from all modules
        foreach ($this->modules as $module) {
            $bindingRegistry->registerModule($module);
        }

        // Determine whether to hydrate from cache or run live scans.
        // The gate reads DiscoveryEnvironment (which reads $_ENV directly) — no marko/config dependency.
        $env = new DiscoveryEnvironment();
        $cache = new DiscoveryCache($projectPaths, $env);
        $useCache = $env->enabled() && $env->environment() !== 'development' && $cache->exists();

        // Load cache payload once (shared across all four subsystem forks).
        // A corrupt cache throws DiscoveryCacheException loudly — no silent fallback.
        /** @var array{preferences: PreferenceRecord[], plugins: PluginDefinition[], observers: ObserverDefinition[], commands: CommandDefinition[]}|null $cachePayload */
        $cachePayload = $useCache ? $cache->load() : null;

        // Fork 1 — preferences (independent of the other three)
        if ($cachePayload !== null) {
            $this->registerPreferencesFromCache($cachePayload['preferences']);
        } else {
            $this->discoverPreferences();
        }

        // Fork 2 — plugins (independent of the other three)
        if ($cachePayload !== null) {
            $this->registerPluginsFromCache($cachePayload['plugins']);
        } else {
            $this->discoverPlugins();
        }

        // Fork 3 — observers (independent; MUST run before EventDispatcher is created below)
        if ($cachePayload !== null) {
            $this->registerObserversFromCache($cachePayload['observers']);
        } else {
            $this->discoverObservers();
        }

        // Create event dispatcher and register in container
        $this->eventDispatcher = new EventDispatcher($this->container, $this->observerRegistry);
        $this->container->instance(EventDispatcherInterface::class, $this->eventDispatcher);

        // Create module repository and register in container
        $moduleRepository = new ModuleRepository($this->modules);
        $this->container->instance(ModuleRepositoryInterface::class, $moduleRepository);

        // Fork 4 — commands (MUST run after module repository is bound above)
        if ($cachePayload !== null) {
            $this->registerCommandsFromCache($cachePayload['commands']);
        } else {
            $this->discoverCommands();
        }

        // Discover and register routes (if routing package is available)
        $this->discoverRoutes();

        // Call module boot callbacks last — the full container is assembled so
        // auto-injected dependencies (via call()) resolve without ordering issues.
        foreach ($this->modules as $module) {
            if ($module->boot !== null) {
                $this->container->call($module->boot);
            }
        }
    }

    /**
     * Register PSR-4 autoloaders for non-vendor modules.
     *
     * Delegates to ModuleAutoloader so lightweight callers (e.g. TestCase)
     * can reuse the same logic without booting the full Application.
     *
     * @throws ModuleException
     */
    private function registerAutoloaders(): void
    {
        $autoloader = new ModuleAutoloader(
            modulesPath: $this->modulesPath,
            appPath: $this->appPath,
            parser: new ManifestParser(),
        );
        $autoloader->register();
    }

    /**
     * @throws PreferenceConflictException
     */
    private function discoverPreferences(): void
    {
        $preferenceDiscovery = new PreferenceDiscovery();

        foreach ($this->modules as $module) {
            $records = $preferenceDiscovery->discoverInModule($module);

            foreach ($records as $record) {
                $this->preferenceRegistry->register(
                    $record->replaces,
                    $record->replacement,
                    $module->name,
                    $module->source,
                );
            }
        }
    }

    /**
     * @throws PluginException|ReflectionException
     */
    private function discoverPlugins(): void
    {
        $pluginDiscovery = new PluginDiscovery();

        foreach ($this->modules as $module) {
            $definitions = $pluginDiscovery->discoverInModule($module);

            foreach ($definitions as $definition) {
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
     * @param PreferenceRecord[] $records
     *
     * @throws PreferenceConflictException
     */
    private function registerPreferencesFromCache(array $records): void
    {
        foreach ($records as $record) {
            $this->preferenceRegistry->register(
                $record->replaces,
                $record->replacement,
            );
        }
    }

    /**
     * @param PluginDefinition[] $definitions
     *
     * @throws PluginException|ReflectionException
     */
    private function registerPluginsFromCache(array $definitions): void
    {
        foreach ($definitions as $definition) {
            $this->pluginRegistry->register($definition);
        }
    }

    /**
     * @param ObserverDefinition[] $definitions
     */
    private function registerObserversFromCache(array $definitions): void
    {
        $this->observerRegistry = new ObserverRegistry();

        foreach ($definitions as $definition) {
            $this->observerRegistry->register($definition);
        }
    }

    /**
     * @param CommandDefinition[] $definitions
     *
     * @throws CommandException
     */
    private function registerCommandsFromCache(array $definitions): void
    {
        $this->commandRegistry = new CommandRegistry();

        foreach ($definitions as $definition) {
            $this->commandRegistry->register($definition);
        }

        // Bind registry in container so commands can inject it
        $this->container->instance(CommandRegistry::class, $this->commandRegistry);

        $this->commandRunner = new CommandRunner($this->container, $this->commandRegistry);
    }

    /**
     * @throws ModuleException|RouteException|RouteConflictException|ReflectionException
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

        $globalMiddleware = $this->discoverGlobalMiddleware();

        $this->_router = $bootstrapper->boot($globalMiddleware);
    }

    /**
     * @throws RuntimeException|ContainerExceptionInterface|ReflectionException
     */
    public function handleRequest(): void
    {
        if ($this->_router === null) {
            throw new RuntimeException(
                'Cannot handle HTTP requests: marko/routing is not installed. Run: composer require marko/routing',
            );
        }

        $request = Request::fromGlobals();
        $response = $this->_router->handle($request);
        $response->send();
    }

    /**
     * Discover available global middleware classes by merging module-declared
     * @return array<class-string<MiddlewareInterface>>
     * @throws ModuleException When a module-declared middleware class is invalid
     */
    private function discoverGlobalMiddleware(): array
    {
        return (new GlobalMiddlewareResolver())->resolve($this->modules);
    }
}
