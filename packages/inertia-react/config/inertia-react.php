<?php

declare(strict_types=1);

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$basePath = is_string($documentRoot) && $documentRoot !== ''
    ? dirname($documentRoot)
    : (getcwd() ?: '');

return [
    'clientEntry' => env('INERTIA_REACT_CLIENT_ENTRY', 'app/react-web/resources/js/app.jsx'),
    'ssrEntry' => env('INERTIA_REACT_SSR_ENTRY', 'app/react-web/resources/js/ssr.jsx'),
    'ssrBundle' => env('INERTIA_REACT_SSR_BUNDLE', $basePath.'/bootstrap/ssr/react/ssr.js'),
];
