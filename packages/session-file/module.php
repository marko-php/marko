<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Factory\FileSessionHandlerFactory;

return [
    'enabled' => true,
    'bindings' => [
        FileSessionHandlerFactory::class => FileSessionHandlerFactory::class,
        SessionHandlerInterface::class => function (ContainerInterface $container): SessionHandlerInterface {
            return $container->get(FileSessionHandlerFactory::class)->create();
        },
    ],
];
