<?php

declare(strict_types=1);

namespace Marko\Config;

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
     * @param array<string> $modulePaths Paths to modules (containing config/ subdirectories)
     * @param string $rootConfigPath Path to the root/app config directory
     */
    public function createRepository(
        array $modulePaths,
        string $rootConfigPath,
    ): ConfigRepositoryInterface {
        $config = $this->discovery->discover(
            modulePaths: $modulePaths,
            rootConfigPath: $rootConfigPath,
        );

        return new ConfigRepository($config);
    }
}
