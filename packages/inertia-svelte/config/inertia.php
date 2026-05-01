<?php

declare(strict_types=1);

return [
    'assetEntry' => env('INERTIA_SVELTE_CLIENT_ENTRY', 'app/svelte-web/resources/js/app.js'),
    'ssr' => [
        'bundle' => env('INERTIA_SVELTE_SSR_BUNDLE', 'bootstrap/ssr/svelte/ssr.js'),
    ],
];
