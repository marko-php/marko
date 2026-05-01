<?php

declare(strict_types=1);

return [
    'assetEntry' => env('INERTIA_REACT_CLIENT_ENTRY', 'app/react-web/resources/js/app.jsx'),
    'ssr' => [
        'bundle' => env('INERTIA_REACT_SSR_BUNDLE', 'bootstrap/ssr/react/ssr.js'),
    ],
];
