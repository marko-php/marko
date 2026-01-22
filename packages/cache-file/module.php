<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Factory\FileCacheFactory;
use Marko\Core\Container\ContainerInterface;

return [
    'enabled' => true,
    'bindings' => [
        FileCacheFactory::class => FileCacheFactory::class,
        CacheInterface::class => function (ContainerInterface $container): CacheInterface {
            return $container->get(FileCacheFactory::class)->create();
        },
    ],
];
