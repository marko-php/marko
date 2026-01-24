<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Manager\FilesystemManager;

return [
    'enabled' => true,
    'bindings' => [
        FilesystemInterface::class => function (ContainerInterface $container): FilesystemInterface {
            return $container->get(FilesystemManager::class)->disk();
        },
    ],
];
