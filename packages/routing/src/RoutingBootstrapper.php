<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Discovery\ClassFileParser;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Module\ModuleManifest;
use Marko\Routing\Discovery\HttpExceptionResponderCacheContributor;
use Marko\Routing\Discovery\HttpExceptionResponderDefinition;
use Marko\Routing\Discovery\HttpExceptionResponderDiscovery;
use Marko\Routing\Exceptions\HttpExceptionResponderException;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\ExceptionRenderer;
use Marko\Routing\Http\HttpExceptionResponderRegistry;
use Marko\Routing\Middleware\MiddlewareInterface;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;

readonly class RoutingBootstrapper
{
    private RouteCollector $routeCollector;

    private RouteCacheContributor $routeCache;

    private HttpExceptionResponderDiscovery $responderDiscovery;

    private HttpExceptionResponderCacheContributor $responderCache;

    /**
     * @param array<ModuleManifest> $modules
     */
    public function __construct(
        private array $modules,
        private ContainerInterface $container,
        PreferenceRegistry $preferenceRegistry,
        ClassFileParser $classFileParser,
    ) {
        $this->routeCollector = new RouteCollector($preferenceRegistry, $classFileParser);
        $this->routeCache = new RouteCacheContributor($this->routeCollector);
        $this->responderDiscovery = new HttpExceptionResponderDiscovery($classFileParser);
        $this->responderCache = new HttpExceptionResponderCacheContributor($this->responderDiscovery);
    }

    /**
     * Bootstrap the routing system: load routes and register the Router in the container.
     *
     * Routes come from the discovery cache when this boot used it (see
     * CachedDiscovery); otherwise every module is scanned.
     *
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     * @throws RouteException|RouteConflictException|ReflectionException|ContainerExceptionInterface|DiscoveryCacheException|HttpExceptionResponderException
     */
    public function boot(
        array $globalMiddleware = [],
    ): Router {
        $routes = $this->loadRoutes();
        $this->assertExcludedMiddlewareExists($routes, $globalMiddleware);

        // Register RouteCollection as singleton instance
        $this->container->instance(RouteCollection::class, $routes);

        // Register a single RouteMatcher so the Router and any middleware that
        // inspects routes (e.g. PageCacheMiddleware) share one memoized instance.
        $matcher = new RouteMatcher($routes);
        $this->container->instance(RouteMatcherInterface::class, $matcher);

        // Register the HTTP exception responders before the renderer is resolved,
        // so the container injects them into ExceptionRenderer (or its Preference).
        $this->container->instance(
            HttpExceptionResponderRegistry::class,
            new HttpExceptionResponderRegistry($this->container, $this->loadResponders()),
        );

        // Create and register Router. The renderer is resolved through the
        // container so an app-level #[Preference] on ExceptionRenderer applies.
        $router = new Router(
            $matcher,
            $this->container,
            $globalMiddleware,
            $this->container->get(ExceptionRenderer::class),
        );
        $this->container->instance(Router::class, $router);

        return $router;
    }

    /**
     * Every middleware a route excludes must be in its stack (global or route
     * middleware), so a typo or an uninstalled package fails at boot instead
     * of silently excluding nothing.
     *
     * @param array<class-string<MiddlewareInterface>> $globalMiddleware
     * @throws RouteException
     */
    private function assertExcludedMiddlewareExists(
        RouteCollection $routes,
        array $globalMiddleware,
    ): void {
        foreach ($routes->all() as $route) {
            $stack = [...$globalMiddleware, ...$route->middleware];

            foreach ($route->withoutMiddleware as $excluded) {
                if (!in_array($excluded, $stack, true)) {
                    throw RouteException::excludedMiddlewareNotInStack($route, $excluded, $stack);
                }
            }
        }
    }

    /**
     * The #[HttpExceptionResponder] definitions, from the discovery cache when
     * this boot used it, otherwise from a scan of every module.
     *
     * @return array<int, HttpExceptionResponderDefinition>
     * @throws ContainerExceptionInterface|DiscoveryCacheException|HttpExceptionResponderException
     */
    private function loadResponders(): array
    {
        $section = $this->container->get(CachedDiscovery::class)->section(HttpExceptionResponderCacheContributor::KEY);

        if ($section !== null) {
            return $this->responderCache->hydrate($section);
        }

        return $this->responderDiscovery->discoverAll($this->modules);
    }

    /**
     * @throws RouteException|RouteConflictException|ReflectionException|ContainerExceptionInterface|DiscoveryCacheException
     */
    private function loadRoutes(): RouteCollection
    {
        // Application binds CachedDiscovery on every boot; anywhere else it autowires uncached.
        $section = $this->container->get(CachedDiscovery::class)->section(RouteCacheContributor::KEY);

        if ($section !== null) {
            return $this->routeCache->hydrate($section);
        }

        return $this->routeCollector->collect($this->modules);
    }
}
