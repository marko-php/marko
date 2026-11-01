<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\ContainerInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Vite\Vite;

return [
    'bindings' => [
        SsrTransportInterface::class => CurlSsrTransport::class,
    ],
    'singletons' => [
        // The errors prop reads flashed validation errors when marko/forms binds the reader
        Inertia::class => function (ContainerInterface $container): Inertia {
            return new Inertia(
                config: $container->get(ConfigRepositoryInterface::class),
                vite: $container->get(Vite::class),
                ssrClient: $container->get(SsrClient::class),
                session: $container->get(SessionInterface::class),
                formState: $container->has(FlashedFormStateInterface::class)
                    ? $container->get(FlashedFormStateInterface::class)
                    : null,
            );
        },
        SsrClient::class,
    ],
];
