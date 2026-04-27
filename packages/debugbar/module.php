<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Debugbar\Debugbar;
use Marko\Debugbar\Plugins\DatabaseConnectionPlugin;
use Marko\Debugbar\Plugins\LoggerPlugin;
use Marko\Debugbar\Plugins\ViewPlugin;
use Marko\Debugbar\Storage\DebugbarStorage;

return [
    'enabled' => true,
    'sequence' => [
        'after' => ['marko/config', 'marko/routing'],
    ],
    'bindings' => [
        DebugbarStorage::class => static function (ContainerInterface $container): DebugbarStorage {
            $config = $container->get(ConfigRepositoryInterface::class);
            $paths = $container->get(ProjectPaths::class);

            if (! $config instanceof ConfigRepositoryInterface) {
                throw new RuntimeException(
                    'Config repository binding must implement '.ConfigRepositoryInterface::class
                );
            }

            if (! $paths instanceof ProjectPaths) {
                throw new RuntimeException('Project paths binding must be '.ProjectPaths::class);
            }

            return new DebugbarStorage($config, $paths);
        },
        Debugbar::class => static function (ContainerInterface $container): Debugbar {
            $config = $container->get(ConfigRepositoryInterface::class);
            $storage = $container->get(DebugbarStorage::class);

            if (! $config instanceof ConfigRepositoryInterface) {
                throw new RuntimeException(
                    'Config repository binding must implement '.ConfigRepositoryInterface::class
                );
            }

            if (! $storage instanceof DebugbarStorage) {
                throw new RuntimeException('Debugbar storage binding must be '.DebugbarStorage::class);
            }

            return new Debugbar($config, $storage);
        },
    ],
    'singletons' => [
        Debugbar::class,
        DebugbarStorage::class,
        DatabaseConnectionPlugin::class,
        LoggerPlugin::class,
        ViewPlugin::class,
    ],
    'boot' => static function (Debugbar $debugbar): void {
        $debugbar->boot();
    },
];
