<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Security\Config\SecurityConfig;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;

return [
    // CsrfMiddleware reads the token from the session, so SessionMiddleware
    // (registered globally by the session drivers) must run first.
    'sequence' => [
        'after' => ['marko/session-file', 'marko/session-database'],
    ],
    'bindings' => [
        CsrfTokenManagerInterface::class => CsrfTokenManager::class,
    ],
    'arguments' => [
        CsrfTokenManager::class => [
            'sessionKey' => fn (ContainerInterface $container): string => $container->get(
                SecurityConfig::class,
            )->csrfSessionKey(),
        ],
    ],
    // Every matched route is protected; opt a route out with
    // #[WithoutMiddleware(CsrfMiddleware::class)] or
    // #[WithoutMiddleware(SecurityHeadersMiddleware::class)].
    // SecurityHeadersMiddleware comes first so it wraps CsrfMiddleware and
    // also decorates the 419 a token mismatch renders.
    'globalMiddleware' => [
        SecurityHeadersMiddleware::class,
        CsrfMiddleware::class,
    ],
];
