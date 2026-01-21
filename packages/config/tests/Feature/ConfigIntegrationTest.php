<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Container\PreferenceRegistry;

beforeEach(function () {
    // Set up fixture paths
    $this->fixturesPath = __DIR__ . '/fixtures';
    $this->moduleAPath = $this->fixturesPath . '/module-a';
    $this->moduleBPath = $this->fixturesPath . '/module-b';
    $this->appConfigPath = $this->fixturesPath . '/app-config';
});

describe('Config Integration', function (): void {
    it('loads config from module config/ directory', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath],
            rootConfigPath: $this->fixturesPath . '/empty-config',
        );

        // Module A provides database config
        expect($repository->get('database.host'))->toBe('localhost')
            ->and($repository->get('database.port'))->toBe(3306);
    });

    it('loads config from app config/ directory', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [],
            rootConfigPath: $this->appConfigPath,
        );

        // App config provides cache settings
        expect($repository->get('cache.driver'))->toBe('redis')
            ->and($repository->get('cache.ttl'))->toBe(7200);
    });

    it('app config overrides module config', function () {
        $container = createContainerWithBindings();

        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath, $this->moduleBPath],
            rootConfigPath: $this->appConfigPath,
        );

        // Module A: host=localhost, port=3306
        // Module B: host=module-b.db.com, name=module_b_db
        // App: host=production.db.com (overrides module config)
        expect($repository->get('database.host'))->toBe('production.db.com')
            ->and($repository->get('database.port'))->toBe(3306)
            ->and($repository->get('database.name'))->toBe('module_b_db');
    });

    it('ConfigRepository injectable via constructor', function () {
        $container = createContainerWithBindings();

        // Create and register the repository
        $provider = $container->get(ConfigServiceProvider::class);
        $repository = $provider->createRepository(
            modulePaths: [$this->moduleAPath],
            rootConfigPath: $this->fixturesPath . '/empty-config',
        );
        $container->instance(ConfigRepositoryInterface::class, $repository);

        // Create a test service that depends on ConfigRepositoryInterface
        $service = $container->get(TestServiceWithConfig::class);

        expect($service)->toBeInstanceOf(TestServiceWithConfig::class)
            ->and($service->getDatabaseHost())->toBe('localhost');
    });
});

/**
 * Helper to create container with config bindings.
 */
function createContainerWithBindings(): Container
{
    $container = new Container(new PreferenceRegistry());

    // Register config bindings (same as module.php)
    $container->bind(ConfigLoader::class, ConfigLoader::class);
    $container->bind(ConfigMerger::class, ConfigMerger::class);
    $container->bind(ConfigDiscovery::class, ConfigDiscovery::class);
    $container->bind(ConfigServiceProvider::class, ConfigServiceProvider::class);
    $container->bind(
        ConfigRepositoryInterface::class,
        fn (ContainerInterface $c) => $c->get(ConfigRepository::class),
    );

    return $container;
}

/**
 * Test service that depends on ConfigRepositoryInterface.
 */
class TestServiceWithConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    public function getDatabaseHost(): string
    {
        return $this->config->getString('database.host');
    }
}
