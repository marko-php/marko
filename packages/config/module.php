<?php

declare(strict_types=1);

use Marko\Config\ConfigRepository;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;

return [
    'enabled' => true,
    'bindings' => [
        ConfigRepositoryInterface::class => function (ContainerInterface $container): ConfigRepositoryInterface {
            return $container->get(ConfigRepository::class);
        },
    ],
];
