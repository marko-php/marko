<?php

declare(strict_types=1);

use Marko\Authorization\Contracts\GateHookInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\GateHookRegistry;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Discovery\ConfigCacheContributor;
use Marko\Config\Exceptions\ConfigCacheException;
use Marko\Core\Application;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleManifest;
use Marko\Database\Entity\EntityCacheContributor;
use Marko\Integration\Fixture\Auth\IntegrationGateHook;
use Marko\Integration\Fixture\Entity\Author;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;

/*
 * The route and module discovery cache through the real module wiring (#173):
 * a production boot from a warm cache must behave exactly like a live boot.
 */

pest()->group('integration-services');

beforeEach(function (): void {
    $this->savedDiscoveryEnv = [];

    foreach (['DISCOVERY_CACHE_ENABLED', 'DISCOVERY_CACHE_PATH'] as $key) {
        $this->savedDiscoveryEnv[$key] = [array_key_exists($key, $_ENV) ? $_ENV[$key] : null, getenv($key)];
        unset($_ENV[$key]);
        putenv($key);
    }

    setUpIntegrationTest($this);
});

afterEach(function (): void {
    tearDownIntegrationTest($this);

    foreach ($this->savedDiscoveryEnv ?? [] as $key => [$env, $process]) {
        unset($_ENV[$key]);
        putenv($key);

        if ($env !== null) {
            $_ENV[$key] = $env;
        }

        if ($process !== false) {
            putenv("$key=$process");
        }
    }
});

/**
 * @return array<int, array<string, mixed>>
 */
function discoveryCacheRoutes(
    Application $app,
): array {
    return array_map(fn (RouteDefinition $route): array => [
        'method' => $route->method,
        'path' => $route->path,
        'controller' => $route->controller,
        'action' => $route->action,
        'middleware' => $route->middleware,
        'name' => $route->name,
        'withoutMiddleware' => $route->withoutMiddleware,
    ], $app->container->get(RouteCollection::class)->inMatchOrder());
}

/**
 * Status and body of requests that leave no state behind.
 *
 * @return array<string, array{int, string}>
 */
function discoveryCacheResponses(
    Application $app,
): array {
    $requests = [
        ['GET', '/health'],
        ['GET', '/visits'],
        ['GET', '/admin'],
        ['GET', '/admin/report'],
        ['GET', '/api/status/204'],
        ['GET', '/api/status/abc'],
        ['POST', '/health'],
        ['GET', '/no-such-route'],
    ];
    $responses = [];

    foreach ($requests as [$method, $uri]) {
        $response = $app->router->handle(integrationRequest($method, $uri));
        $responses["$method $uri"] = [$response->statusCode(), $response->body()];
    }

    return $responses;
}

it('routes identically with the discovery cache warm and cold', function (): void {
    $cold = $this->app;
    $coldRoutes = discoveryCacheRoutes($cold);
    $coldResponses = discoveryCacheResponses($cold);

    // Built in the environment it boots in: a changed APP_ENV fails the cached boot (#618)
    $result = withIntegrationAppEnv('production', fn (): array => runIntegrationCommand($cold, 'discovery:cache'));
    expect($result['exitCode'])->toBe(0, $result['output']);

    $warm = withIntegrationAppEnv('production', function (): Application {
        $app = Application::boot($this->project);
        restore_error_handler();
        restore_exception_handler();

        return $app;
    });
    $cachedDiscovery = $warm->container->get(CachedDiscovery::class);

    expect($cachedDiscovery->isCached())->toBeTrue()
        ->and(array_map(fn (ModuleManifest $m): string => $m->name, $warm->modules))
        ->toBe(array_map(fn (ModuleManifest $m): string => $m->name, $cold->modules))
        ->and(discoveryCacheRoutes($warm))->toBe($coldRoutes)
        ->and($cachedDiscovery->section(EntityCacheContributor::KEY))->toContain(Author::class)
        ->and($cachedDiscovery->section(GateHookCacheContributor::KEY))
        ->toBe([['className' => IntegrationGateHook::class, 'priority' => 10]])
        ->and(array_map(
            fn (GateHookInterface $hook): string => $hook::class,
            $warm->container->get(GateHookRegistry::class)->hooks(),
        ))->toBe([IntegrationGateHook::class])
        ->and(discoveryCacheResponses($warm))->toBe($coldResponses);
})->issue(173);

it('loads config from the discovery cache on a warm boot and live again after discovery:clear', function (): void {
    $bootProduction = fn (): Application => withIntegrationAppEnv('production', function (): Application {
        $app = Application::boot($this->project);
        restore_error_handler();
        restore_exception_handler();

        return $app;
    });

    $liveConfig = $bootProduction()->container->get(ConfigRepositoryInterface::class)->all();

    // Built in the environment it boots in: a changed APP_ENV fails the cached boot (#618)
    $result = withIntegrationAppEnv(
        'production',
        fn (): array => runIntegrationCommand($this->app, 'discovery:cache'),
    );
    expect($result['exitCode'])->toBe(0, $result['output']);

    $warm = $bootProduction();
    $warmCachedDiscovery = $warm->container->get(CachedDiscovery::class);

    expect($warmCachedDiscovery->isCached())->toBeTrue()
        ->and($warmCachedDiscovery->section(ConfigCacheContributor::KEY)['values'])->toBe($liveConfig)
        ->and($warm->container->get(ConfigRepositoryInterface::class)->all())->toBe($liveConfig);

    $result = runIntegrationCommand($warm, 'discovery:clear');
    expect($result['exitCode'])->toBe(0, $result['output']);

    $live = $bootProduction();

    expect($live->container->get(CachedDiscovery::class)->isCached())->toBeFalse()
        ->and($live->container->get(ConfigRepositoryInterface::class)->all())->toBe($liveConfig);
})->issue(556);

it('fails the cached boot naming APP_ENV when the cache was built in another environment', function (): void {
    $result = withIntegrationAppEnv('staging', fn (): array => runIntegrationCommand($this->app, 'discovery:cache'));
    expect($result['exitCode'])->toBe(0, $result['output']);

    $thrown = null;

    try {
        withIntegrationAppEnv('production', fn (): Application => Application::boot($this->project));
    } catch (ConfigCacheException $exception) {
        $thrown = $exception;
    } finally {
        // The failed boot leaves its bootstrap exception handler installed; hand control back to PHPUnit
        restore_exception_handler();
    }

    expect($thrown)->toBeInstanceOf(ConfigCacheException::class)
        ->and($thrown?->getMessage())->toBe(
            'Environment variables changed since the discovery cache was built: APP_ENV',
        );
})->issue(618);
