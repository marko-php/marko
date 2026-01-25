<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Hashing\HashManager;

return [
    'bindings' => [
        HasherInterface::class => function (ContainerInterface $container): HasherInterface {
            return $container->get(HashManager::class)->hasher();
        },
    ],
];
