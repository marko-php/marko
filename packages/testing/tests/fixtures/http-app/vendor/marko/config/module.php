<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;
use Marko\Config\Discovery\ConfigCacheContributor;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Core\Path\ProjectPaths;

return [
    'bindings' => [
        ConfigRepositoryInterface::class => static function (ContainerInterface $container): ConfigRepositoryInterface {
            $provider = $container->get(ConfigServiceProvider::class);
            $modules = $container->get(ModuleRepositoryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            $modulePaths = array_map(
                fn ($module) => $module->path,
                $modules->all(),
            );

            return $provider->createRepository(
                modulePaths: $modulePaths,
                rootConfigPath: $paths->config,
                cachedDiscovery: $container->get(CachedDiscovery::class),
            );
        },
    ],
    'singletons' => [
        ConfigRepositoryInterface::class,
    ],
    'discovery' => [
        ConfigCacheContributor::class,
    ],
];
