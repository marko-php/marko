<?php

declare(strict_types=1);

use Marko\AdminAuth\AdminUserProvider;
use Marko\AdminAuth\Config\AdminAuthConfig;
use Marko\AdminAuth\Config\AdminAuthConfigInterface;
use Marko\AdminAuth\Repository\AdminUserRepository;
use Marko\AdminAuth\Repository\AdminUserRepositoryInterface;
use Marko\AdminAuth\Repository\PermissionRepository;
use Marko\AdminAuth\Repository\PermissionRepositoryInterface;
use Marko\AdminAuth\Repository\RoleRepository;
use Marko\AdminAuth\Repository\RoleRepositoryInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        AdminAuthConfigInterface::class => AdminAuthConfig::class,
        AdminUserRepositoryInterface::class => AdminUserRepository::class,
        RoleRepositoryInterface::class => RoleRepository::class,
        PermissionRepositoryInterface::class => PermissionRepository::class,
        UserProviderInterface::class => function (ContainerInterface $container): UserProviderInterface {
            return new AdminUserProvider(
                userRepository: $container->get(AdminUserRepositoryInterface::class),
                roleRepository: $container->get(RoleRepositoryInterface::class),
                passwordHasher: $container->get(PasswordHasherInterface::class),
            );
        },
    ],
];
