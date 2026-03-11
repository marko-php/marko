<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Gate;
use Marko\Authorization\PolicyRegistry;
use Marko\Core\Container\ContainerInterface;

return [
    'bindings' => [
        GateInterface::class => function (ContainerInterface $container): GateInterface {
            $authManager = $container->get(AuthManager::class);
            $config = $container->get(AuthorizationConfig::class);
            $defaultGuard = $config->defaultGuard();

            return new Gate(
                guard: $authManager->guard($defaultGuard),
                policyRegistry: $container->get(PolicyRegistry::class),
            );
        },
    ],
    'singletons' => [
        PolicyRegistry::class,
        GateInterface::class,
    ],
];
