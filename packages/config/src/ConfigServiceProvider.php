<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Discovery\ConfigCacheContributor;
use Marko\Config\Discovery\ConfigCacheSection;
use Marko\Config\Exceptions\ConfigCacheException;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigLoadException;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Exceptions\DiscoveryCacheException;

/**
 * Service provider for configuration management.
 *
 * Integration with Application Boot Process:
 * -----------------------------------------
 * Config loading should happen early in the Application boot sequence:
 *
 * 1. Module discovery (find all modules)
 * 2. Dependency resolution (sort modules by dependencies)
 * 3. Register autoloaders
 * 4. Initialize container and bind services
 * 5. >>> CONFIG LOADING HAPPENS HERE <<<
 * 6. Discover preferences, plugins, observers
 * 7. Discover commands and routes
 *
 * The ConfigRepository should be registered as a SINGLETON in the container
 * to ensure all services share the same configuration state and to avoid
 * re-discovering and re-merging config files on every resolution.
 *
 * Example integration in Application::boot():
 *
 *     // After bindings are registered, bootstrap config
 *     $configProvider = $this->container->get(ConfigServiceProvider::class);
 *     $modulePaths = array_map(fn($m) => $m->path, $this->modules);
 *     $configRepository = $configProvider->createRepository(
 *         modulePaths: $modulePaths,
 *         rootConfigPath: $this->appPath . '/config',
 *     );
 *     $this->container->instance(ConfigRepositoryInterface::class, $configRepository);
 *     $this->container->singleton(ConfigRepositoryInterface::class);
 */
readonly class ConfigServiceProvider
{
    public function __construct(
        private ConfigDiscovery $discovery,
        private ConfigCacheSection $cacheSection = new ConfigCacheSection(),
    ) {}

    /**
     * Create a ConfigRepository from discovered config files.
     *
     * Discovers config files from:
     * 1. Module config/ directories (in module load order)
     * 2. Root/app config directory (highest priority, overrides everything)
     *
     * Later modules override earlier modules. App config overrides all modules.
     *
     * When this boot used the discovery cache, the repository is built from its
     * config section instead and no config file is found, required or merged.
     *
     * @param array<string> $modulePaths Paths to modules (containing config/ subdirectories)
     * @param string $rootConfigPath Path to the root/app config directory
     * @param CachedDiscovery|null $cachedDiscovery This boot's discovery cache sections; null loads config live
     * @throws ConfigLoadException|ConfigCacheException|ConfigException|DiscoveryCacheException
     */
    public function createRepository(
        array $modulePaths,
        string $rootConfigPath,
        ?CachedDiscovery $cachedDiscovery = null,
    ): ConfigRepositoryInterface {
        $section = $cachedDiscovery?->section(ConfigCacheContributor::KEY);

        if ($section !== null) {
            return new ConfigRepository($this->cacheSection->values($section));
        }

        $config = $this->discovery->discover(
            modulePaths: $modulePaths,
            rootConfigPath: $rootConfigPath,
        );

        return new ConfigRepository($config);
    }
}
