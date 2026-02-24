<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Session\Contracts\SessionInterface;

return [
    'bindings' => [
        CsrfTokenManagerInterface::class => function (ContainerInterface $container): CsrfTokenManagerInterface {
            return new CsrfTokenManager(
                session: $container->get(SessionInterface::class),
                encryptor: $container->get(EncryptorInterface::class),
            );
        },
    ],
];
