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
    // (registered globally by the session drivers) must run first. Loading
    // before marko/layout keeps CsrfMiddleware around LayoutMiddleware when
    // both run innermost, so a token a layout component issues is mirrored to
    // the XSRF-TOKEN cookie.
    'sequence' => [
        'after' => ['marko/session-file', 'marko/session-database'],
        'before' => ['marko/layout'],
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
    'singletons' => [
        // Shared, so CsrfMiddleware still accepts the token a request arrived
        // with after a login in route middleware rotated it.
        CsrfTokenManagerInterface::class,
    ],
    // Every matched route is protected; opt a route out with
    // #[WithoutMiddleware(CsrfMiddleware::class)] or
    // #[WithoutMiddleware(SecurityHeadersMiddleware::class)].
    // CsrfMiddleware runs innermost (#[RunsInnermost]), so SecurityHeadersMiddleware
    // wraps it and also decorates the 419 a token mismatch renders.
    'globalMiddleware' => [
        SecurityHeadersMiddleware::class,
        CsrfMiddleware::class,
    ],
];
