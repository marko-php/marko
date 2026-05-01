<?php

declare(strict_types=1);

use Marko\Inertia\Inertia;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;

return [
    'bindings' => [
        SsrTransportInterface::class => CurlSsrTransport::class,
    ],
    'singletons' => [
        Inertia::class,
        SsrClient::class,
    ],
];
