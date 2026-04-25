<?php

declare(strict_types=1);

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$basePath = is_string($documentRoot) && $documentRoot !== ''
    ? dirname($documentRoot)
    : (getcwd() ?: '');

return [
    'clientEntry' => env('INERTIA_SVELTE_CLIENT_ENTRY', 'app/svelte-web/resources/js/app.js'),
    'ssrEntry' => env('INERTIA_SVELTE_SSR_ENTRY', 'app/svelte-web/resources/js/ssr.js'),
    'ssrBundle' => env('INERTIA_SVELTE_SSR_BUNDLE', $basePath.'/bootstrap/ssr/svelte/ssr.js'),
];
