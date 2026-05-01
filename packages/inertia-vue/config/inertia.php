<?php

declare(strict_types=1);

return [
    'assetEntry' => env('INERTIA_VUE_CLIENT_ENTRY', 'app/vue-web/resources/js/app.js'),
    'ssr' => [
        'bundle' => env('INERTIA_VUE_SSR_BUNDLE', 'bootstrap/ssr/vue/ssr.js'),
    ],
];
