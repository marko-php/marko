<?php

declare(strict_types=1);

namespace Marko\Core;

use Marko\Core\Command\CommandDefinition;
use Marko\Core\Command\CommandDiscovery;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\CommandRunner;
use Marko\Core\Command\ConfirmationPrompterInterface;
use Marko\Core\Command\StdinConfirmationPrompter;
use Marko\Core\Container\BindingRegistry;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceDiscovery;
use Marko\Core\Container\PreferenceRecord;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Discovery\DiscoveryCache;
use Marko\Core\Discovery\DiscoveryEnvironment;
use Marko\Core\Environment\AppEnvironment;
use Marko\Core\Error\BootstrapErrorHandler;
use Marko\Core\Event\ApplicationBooted;
use Marko\Core\Event\EventDispatcher;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Core\Event\ObserverDefinition;
use Marko\Core\Event\ObserverDiscovery;
use Marko\Core\Event\ObserverRegistry;
use Marko\Core\Exceptions\ArgumentConflictException;
use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Core\Module\CachedModule;
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
use Marko\Core\Sleep\SleeperInterface;
use Marko\Core\Sleep\UsleepSleeper;
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

    private bool $shutDown = false;

    /** @var Router */
    public object $router {
        get => $this->_router ?? throw new RuntimeException(
            'Router not available. Install marko/routing: composer require marko/routing',
        );
    }

    /**
     * The module discovery, manifest parser and class file parser are injectable
     * so tests can observe that a discovery-cache boot never touches them.
     *
     * The bootstrap error handler guards initialize() until an errors module
     * registers its own handler.
     */
    public function __construct(
        public private(set) readonly string $vendorPath = '',
        public private(set) readonly string $modulesPath = '',
        public private(set) readonly string $appPath = '',
        private readonly ManifestParser $manifestParser = new ManifestParser(),
        private readonly ModuleDiscovery $moduleDiscovery = new ModuleDiscovery(new ManifestParser()),
        private readonly ClassFileParser $classFileParser = new ClassFileParser(),
        public readonly BootstrapErrorHandler $bootstrapErrorHandler = new BootstrapErrorHandler(),
    ) {}

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException|ArgumentException|ArgumentConflictException
     */
    public static function boot(
        string $basePath,
    ): self {
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
     * @param bool $useDiscoveryCache False boots from live discovery even when a discovery cache exists
     *                                (used by discovery:cache and discovery:clear, which must work while the cache is stale)
     *
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|DiscoveryCacheException|ArgumentException|ArgumentConflictException
     */
    public function initialize(
        bool $useDiscoveryCache = true,
    ): void {
        // Before anything else can throw: a boot-time exception must never reach PHP's native
        // "Uncaught ... Stack trace" output. An errors module's boot callback replaces this
        // handler; without one it is removed again once boot completes.
        $this->bootstrapErrorHandler->register();

        // Project paths derive from the vendor path
        $basePath = dirname($this->vendorPath);
        $projectPaths = new ProjectPaths($basePath);

        // Load environment variables if marko/env is installed
        if (class_exists(EnvLoader::class)) {
            (new EnvLoader())->load($basePath);
        }

        // .env may have set the environment, which decides whether display_errors stays off
        $this->bootstrapErrorHandler->applyDisplayErrors();

        // One shared answer to "which environment is this?" for the whole application
        $appEnvironment = new AppEnvironment();

        // Decide before module discovery whether to hydrate from the cache or run live scans.
        // The gate reads DiscoveryEnvironment ($_ENV with getenv() fallback) — no marko/config dependency.
        $env = new DiscoveryEnvironment($appEnvironment);
        $cache = new DiscoveryCache($projectPaths, $env);
        $useCache = $useDiscoveryCache && $env->enabled() && !$appEnvironment->isDevelopment() && $cache->exists();

        // Load the cache payload once. A corrupt, version-mismatched or stale cache throws
        // DiscoveryCacheException loudly — no silent fallback, no silent rebuild.
        $cachePayload = $useCache ? $cache->load() : null;

        if ($cachePayload !== null) {
            // Cached boot: no vendor scan, no composer.json parsing, no dependency sort.
            $this->modules = $this->modulesFromCache($cachePayload['modules'], $cache->path());
            $this->registerAutoloadersFor($this->modules);
        } else {
            $this->modules = new DependencyResolver()->resolve(array_merge(
                $this->moduleDiscovery->discoverInVendor($this->vendorPath),
                $this->moduleDiscovery->discoverInModules($this->modulesPath),
                $this->moduleDiscovery->discoverInApp($this->appPath),
            ));
            $this->registerAutoloaders();
        }

        // Initialize container and registries
        $this->preferenceRegistry = new PreferenceRegistry();
        $this->pluginRegistry = new PluginRegistry();
        $container = new Container($this->preferenceRegistry);
        $this->container = $container;
        $this->container->instance(ContainerInterface::class, $this->container);
        $this->container->instance(PreferenceRegistry::class, $this->preferenceRegistry);
        $this->container->instance(ClassFileParser::class, $this->classFileParser);
        $interceptor = new PluginInterceptor($this->container, $this->pluginRegistry, new InterceptorClassGenerator());
        $this->container->setPluginInterceptor($interceptor);
        $this->container->instance(PluginInterceptor::class, $interceptor);
        $this->container->instance(PluginRegistry::class, $this->pluginRegistry);
        $bindingRegistry = new BindingRegistry($this->container);

        // Register ProjectPaths for dependency injection
        $this->container->instance(ProjectPaths::class, $projectPaths);

        // Core defaults, bound before module bindings so a module can replace them
        $this->container->bind(ConfirmationPrompterInterface::class, StdinConfirmationPrompter::class);
        $this->container->bind(SleeperInterface::class, UsleepSleeper::class);

        // Register bindings from all modules
        foreach ($this->modules as $module) {
            $bindingRegistry->registerModule($module);
        }

        $this->container->instance(AppEnvironment::class, $appEnvironment);
        $this->container->instance(BootstrapErrorHandler::class, $this->bootstrapErrorHandler);

        // Contributor sections (routes, entities, ...) for the packages that own them
        $this->container->instance(
            CachedDiscovery::class,
            $cachePayload !== null
                ? new CachedDiscovery($cachePayload['sections'], $cache->path())
                : new CachedDiscovery(),
        );

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
        $this->discoverRoutes($cachePayload['globalMiddleware'] ?? null);

        // Call module boot callbacks last — the full container is assembled so
        // auto-injected dependencies (via call()) resolve without ordering issues.
        foreach ($this->modules as $module) {
            if ($module->boot !== null) {
                $this->container->call($module->boot);
            }
        }

        // Every configured class is checked once the boot callbacks (which may bind) have run.
        $container->validateArguments();

        // Observers that check what any module's boot callback set up run once everything has booted.
        $this->eventDispatcher->dispatch(new ApplicationBooted());

        // Boot succeeded; the bootstrap handler is only meant to cover boot itself
        $this->bootstrapErrorHandler->unregister();
    }

    /**
     * Undo the global state boot installed, so the process can go on without this application.
     *
     * Runs every module's `shutdown` callback (from module.php) in reverse module load order,
     * the mirror of the boot callbacks, so handlers pushed onto PHP's global stacks come off
     * in the order they went on. The errors modules unregister their error and exception
     * handlers here. Then removes the bootstrap error handler if a failed boot left it installed.
     *
     * Call it when the application is done but the process is not: marko/testing calls it
     * after each test that booted one, and a long-running worker can call it before it exits
     * or reboots. Safe to call more than once; only the first call does anything.
     *
     * @throws BindingException|CircularDependencyException|ReflectionException|PluginException|ContainerExceptionInterface
     */
    public function shutdown(): void
    {
        if ($this->shutDown) {
            return;
        }

        $this->shutDown = true;

        // initialize() never ran (or failed before the container existed): nothing booted
        if (isset($this->container)) {
            foreach (array_reverse($this->modules) as $module) {
                if ($module->shutdown !== null) {
                    $this->container->call($module->shutdown);
                }
            }
        }

        $this->bootstrapErrorHandler->unregister();
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
            parser: $this->manifestParser,
        );
        $autoloader->register();
    }

    /**
     * Register PSR-4 autoloaders for the non-vendor modules of a cached boot, without discovery.
     *
     * @param array<ModuleManifest> $modules
     */
    private function registerAutoloadersFor(
        array $modules,
    ): void {
        new ModuleAutoloader(
            modulesPath: $this->modulesPath,
            appPath: $this->appPath,
            parser: $this->manifestParser,
        )->registerModules($modules);
    }

    /**
     * Rebuild the module list from the discovery cache. Each module.php is
     * still required so its closures stay live; a module.php that no longer
     * matches what the cache was compiled from makes the cache stale.
     *
     * @param array<CachedModule> $cachedModules
     * @return array<ModuleManifest>
     *
     * @throws ModuleException|DiscoveryCacheException
     */
    private function modulesFromCache(
        array $cachedModules,
        string $cachePath,
    ): array {
        $modules = [];

        foreach ($cachedModules as $cachedModule) {
            $manifest = $this->manifestParser->parseCached($cachedModule);

            if (!$cachedModule->matchesLiveManifest($manifest)) {
                throw DiscoveryCacheException::stale(
                    $cachePath,
                    "module.php of '$cachedModule->name' changed its enabled flag, sequence or globalMiddleware since it was compiled",
                );
            }

            $modules[] = $manifest;
        }

        return $modules;
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
    private function registerPreferencesFromCache(
        array $records,
    ): void {
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
    private function registerPluginsFromCache(
        array $definitions,
    ): void {
        foreach ($definitions as $definition) {
            $this->pluginRegistry->register($definition);
        }
    }

    /**
     * @param ObserverDefinition[] $definitions
     */
    private function registerObserversFromCache(
        array $definitions,
    ): void {
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
    private function registerCommandsFromCache(
        array $definitions,
    ): void {
        $this->commandRegistry = new CommandRegistry();

        foreach ($definitions as $definition) {
            $this->commandRegistry->register($definition);
        }

        // Bind registry in container so commands can inject it
        $this->container->instance(CommandRegistry::class, $this->commandRegistry);

        $this->commandRunner = new CommandRunner($this->container, $this->commandRegistry);
    }

    /**
     * @param array<int, string>|null $cachedGlobalMiddleware The global middleware order from the discovery cache, or null on a live boot
     *
     * @throws ModuleException|RouteException|RouteConflictException|ReflectionException|ContainerExceptionInterface
     */
    private function discoverRoutes(
        ?array $cachedGlobalMiddleware,
    ): void {
        // Only bootstrap routing if the routing package is available
        if (!class_exists(RoutingBootstrapper::class)) {
            return;
        }

        $bootstrapper = new RoutingBootstrapper(
            $this->modules,
            $this->container,
            $this->preferenceRegistry,
            $this->classFileParser,
        );

        /** @var array<class-string<MiddlewareInterface>> $globalMiddleware */
        $globalMiddleware = $cachedGlobalMiddleware ?? $this->discoverGlobalMiddleware();

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
