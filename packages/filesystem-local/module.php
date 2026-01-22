<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Local\Factory\LocalFilesystemFactory;
use Marko\Filesystem\Manager\FilesystemManager;

return [
    'enabled' => true,
    'bindings' => [
        LocalFilesystemFactory::class => LocalFilesystemFactory::class,
        FilesystemInterface::class => function (ContainerInterface $container): FilesystemInterface {
            $manager = $container->get(FilesystemManager::class);
            $manager->registerDriver('local', LocalFilesystemFactory::class);

            return $manager->disk();
        },
    ],
];
