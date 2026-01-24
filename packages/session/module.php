<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Session;

return [
    'enabled' => true,
    'bindings' => [
        SessionInterface::class => function (ContainerInterface $container): SessionInterface {
            return new Session(
                $container->get(SessionHandlerInterface::class),
                $container->get(SessionConfig::class),
            );
        },
    ],
];
