<?php

declare(strict_types=1);

return [
    'enabled' => true,
    'entrypoints' => [
        'css' => 'resources/css/app.css',
    ],
    'auto_include_with_vite' => true,
    'content_paths' => [
        'app/**/*.php',
        'app/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'app/**/resources/views/**/*.blade.php',
        'app/**/resources/views/**/*.latte',
        'app/**/resources/views/**/*.php',
        'modules/**/*.php',
        'modules/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'modules/**/resources/views/**/*.blade.php',
        'modules/**/resources/views/**/*.latte',
        'modules/**/resources/views/**/*.php',
        'resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'resources/views/**/*.blade.php',
        'resources/views/**/*.latte',
        'resources/views/**/*.php',
        'vendor/marko/**/resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'vendor/marko/**/resources/views/**/*.blade.php',
        'vendor/marko/**/resources/views/**/*.latte',
        'vendor/marko/**/resources/views/**/*.php',
    ],
    'extra_content_paths' => [],
    'content_path_providers' => [],
];
