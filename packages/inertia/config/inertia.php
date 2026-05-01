<?php

declare(strict_types=1);

return [
    'version' => null,
    'assetEntry' => null,
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://localhost:13714'),
        'bundle' => null,
    ],
];
