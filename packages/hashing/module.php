<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Hashing\Config\HashConfig;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\Factory\HasherFactory;
use Marko\Hashing\HashManager;

return [
    'enabled' => true,
    'bindings' => [
        HashConfig::class => HashConfig::class,
        HasherFactory::class => HasherFactory::class,
        HashManager::class => HashManager::class,
        HasherInterface::class => function (ContainerInterface $container): HasherInterface {
            return $container->get(HashManager::class)->hasher();
        },
    ],
];
