<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Log\Contracts\LoggerInterface;
use Marko\Log\File\Factory\FileLoggerFactory;

return [
    'enabled' => true,
    'bindings' => [
        LoggerInterface::class => function (ContainerInterface $container): LoggerInterface {
            return $container->get(FileLoggerFactory::class)->create();
        },
    ],
];
