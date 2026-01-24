<?php

declare(strict_types=1);

use Marko\Config\ConfigDiscovery;
use Marko\Config\ConfigLoader;
use Marko\Config\ConfigMerger;
use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\ConfigServiceProvider;
use Marko\Core\Container\ContainerInterface;

return [
    'enabled' => true,
    'bindings' => [
        ConfigRepositoryInterface::class => function (ContainerInterface $container): ConfigRepositoryInterface {
            return $container->get(ConfigRepository::class);
        },
        ConfigLoader::class => ConfigLoader::class,
        ConfigMerger::class => ConfigMerger::class,
        ConfigDiscovery::class => ConfigDiscovery::class,
        ConfigServiceProvider::class => ConfigServiceProvider::class,
    ],
];
