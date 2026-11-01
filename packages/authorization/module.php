<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authorization\Config\AuthorizationConfig;
use Marko\Authorization\Contracts\GateInterface;
use Marko\Authorization\Discovery\GateHookCacheContributor;
use Marko\Authorization\Gate;
use Marko\Authorization\GateHookRegistry;
use Marko\Authorization\Middleware\AuthorizationMiddleware;
use Marko\Authorization\PolicyRegistry;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\EventDispatcherInterface;

return [
    // SessionMiddleware is registered globally by the session drivers; it must run first.
    'sequence' => [
        'after' => ['marko/session-file', 'marko/session-database'],
    ],
    'bindings' => [
        GateInterface::class => function (ContainerInterface $container): GateInterface {
            $authManager = $container->get(AuthManager::class);
            $config = $container->get(AuthorizationConfig::class);
            $defaultGuard = $config->defaultGuard();

            return new Gate(
                guard: $authManager->guard($defaultGuard),
                policyRegistry: $container->get(PolicyRegistry::class),
                gateHookRegistry: $container->get(GateHookRegistry::class),
                eventDispatcher: $container->get(EventDispatcherInterface::class),
            );
        },
        // Lazy: the Gate and guard are built only when a route with #[Can] is matched.
        // Authentication is checked against the same guard the Gate authorizes with.
        AuthorizationMiddleware::class => function (ContainerInterface $container): AuthorizationMiddleware {
            return new AuthorizationMiddleware(
                gate: fn (): GateInterface => $container->get(GateInterface::class),
                guard: fn (): GuardInterface => $container->get(AuthManager::class)->guard(
                    $container->get(AuthorizationConfig::class)->defaultGuard(),
                ),
            );
        },
    ],
    'singletons' => [
        PolicyRegistry::class,
        // Shared: hooks are discovered (or read from the cache) and built once per process.
        GateHookRegistry::class,
        GateInterface::class,
        AuthorizationMiddleware::class,
    ],
    'discovery' => [
        GateHookCacheContributor::class,
    ],
    'globalMiddleware' => [
        AuthorizationMiddleware::class,
    ],
];
