<?php

declare(strict_types=1);

return [
    'rootView' => 'app',
    'version' => null,
    'ssr' => [
        'enabled' => env('INERTIA_SSR_ENABLED', false),
        'url' => env('INERTIA_SSR_URL', 'http://localhost:13714'),
    ],
];
