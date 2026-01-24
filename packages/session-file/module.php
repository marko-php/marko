<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\File\Factory\FileSessionHandlerFactory;

return [
    'enabled' => true,
    'bindings' => [
        SessionHandlerInterface::class => function (ContainerInterface $container): SessionHandlerInterface {
            return $container->get(FileSessionHandlerFactory::class)->create();
        },
    ],
];
