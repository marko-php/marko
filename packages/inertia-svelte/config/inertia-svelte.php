<?php

declare(strict_types=1);

return [
    'clientEntry' => env('INERTIA_SVELTE_CLIENT_ENTRY', 'app/svelte-web/resources/js/app.js'),
    'ssrEntry' => env('INERTIA_SVELTE_SSR_ENTRY', 'app/svelte-web/resources/js/ssr.js'),
    'ssrBundle' => env('INERTIA_SVELTE_SSR_BUNDLE', 'bootstrap/ssr/svelte/ssr.js'),
];
