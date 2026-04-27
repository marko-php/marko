<?php

declare(strict_types=1);

return [
    'enabled' => env('DEBUGBAR_ENABLED', env('APP_DEBUG', false)),
    'inject' => env('DEBUGBAR_INJECT', true),
    'capture_cli' => env('DEBUGBAR_CAPTURE_CLI', false),
    'theme' => env('DEBUGBAR_THEME', 'auto'),
    'route' => [
        'prefix' => env('DEBUGBAR_ROUTE_PREFIX', '_debugbar'),
        'open' => env('DEBUGBAR_ROUTE_OPEN', false),
        'allowed_ips' => ['127.0.0.1', '::1'],
    ],
    'storage' => [
        'enabled' => env('DEBUGBAR_STORAGE_ENABLED', true),
        'path' => env('DEBUGBAR_STORAGE_PATH', 'storage/debugbar'),
        'max_files' => env('DEBUGBAR_STORAGE_MAX_FILES', 100),
    ],
    'collectors' => [
        'messages' => env('DEBUGBAR_COLLECTORS_MESSAGES', true),
        'time' => env('DEBUGBAR_COLLECTORS_TIME', true),
        'memory' => env('DEBUGBAR_COLLECTORS_MEMORY', true),
        'request' => env('DEBUGBAR_COLLECTORS_REQUEST', true),
        'response' => env('DEBUGBAR_COLLECTORS_RESPONSE', true),
        'inertia' => env('DEBUGBAR_COLLECTORS_INERTIA', true),
        'views' => env('DEBUGBAR_COLLECTORS_VIEWS', true),
        'database' => env('DEBUGBAR_COLLECTORS_DATABASE', true),
        'logs' => env('DEBUGBAR_COLLECTORS_LOGS', true),
        'config' => env('DEBUGBAR_COLLECTORS_CONFIG', false),
    ],
    'options' => [
        'messages' => [
            'trace' => env('DEBUGBAR_OPTIONS_MESSAGES_TRACE', false),
        ],
        'config' => [
            'masked' => [
                '*.key',
                '*.password',
                '*.secret',
                '*.token',
                '*.api_key',
                '*.private_key',
            ],
        ],
        'database' => [
            'with_bindings' => env('DEBUGBAR_OPTIONS_DATABASE_WITH_BINDINGS', true),
            'slow_threshold_ms' => env('DEBUGBAR_OPTIONS_DATABASE_SLOW_THRESHOLD_MS', 100),
        ],
    ],
];
