<?php

declare(strict_types=1);

use Marko\Auth\AuthManager;
use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Contracts\PasswordHasherInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Hashing\BcryptPasswordHasher;
use Marko\Core\Container\ContainerInterface;
use Marko\Session\Contracts\SessionInterface;

return [
    'enabled' => true,
    'bindings' => [
        PasswordHasherInterface::class => function (ContainerInterface $container): PasswordHasherInterface {
            $config = $container->get(AuthConfig::class);

            return new BcryptPasswordHasher(
                cost: $config->bcryptCost(),
            );
        },
        AuthManager::class => function (ContainerInterface $container): AuthManager {
            return new AuthManager(
                config: $container->get(AuthConfig::class),
                session: $container->get(SessionInterface::class),
                provider: $container->get(UserProviderInterface::class),
            );
        },
    ],
];
