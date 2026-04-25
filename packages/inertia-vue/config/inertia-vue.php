<?php

declare(strict_types=1);

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$basePath = is_string($documentRoot) && $documentRoot !== ''
    ? dirname($documentRoot)
    : (getcwd() ?: '');

return [
    'clientEntry' => env('INERTIA_VUE_CLIENT_ENTRY', 'app/vue-web/resources/js/app.js'),
    'ssrEntry' => env('INERTIA_VUE_SSR_ENTRY', 'app/vue-web/resources/js/ssr.js'),
    'ssrBundle' => env('INERTIA_VUE_SSR_BUNDLE', $basePath.'/bootstrap/ssr/vue/ssr.js'),
];
