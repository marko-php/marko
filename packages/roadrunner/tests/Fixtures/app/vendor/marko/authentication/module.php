<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Hashing\BcryptPasswordHasher;
use Marko\Core\Container\ContainerInterface;

// Mirrors packages/authentication/module.php — AuthManager and GuardInterface
// as singletons are precisely what task 005/006 hunt for leaks in.
return [
    'bindings' => [
        PasswordHasherInterface::class => function (ContainerInterface $container): PasswordHasherInterface {
            $config = $container->get(AuthConfig::class);

            return new BcryptPasswordHasher(
                cost: $config->bcryptCost(),
            );
        },
        GuardInterface::class => function (ContainerInterface $container): GuardInterface {
            return $container->get(AuthManager::class)->guard();
        },
    ],
    'singletons' => [
        AuthManager::class,
        GuardInterface::class,
    ],
];
