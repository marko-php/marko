<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Validation\Contracts\FlashedFormStateInterface;

return [
    'bindings' => [
        SsrTransportInterface::class => CurlSsrTransport::class,
    ],
    'singletons' => [
        Inertia::class,
        SsrClient::class,
    ],
    'arguments' => [
        Inertia::class => [
            // The errors prop reads flashed validation errors when marko/forms binds the reader
            'formState' => fn (ContainerInterface $container): ?FlashedFormStateInterface => $container->has(
                FlashedFormStateInterface::class,
            )
                ? $container->get(FlashedFormStateInterface::class)
                : null,
        ],
    ],
];
