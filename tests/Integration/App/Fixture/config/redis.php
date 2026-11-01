<?php

declare(strict_types=1);

// What an application writes to point marko/redis at its server: the
// documented $_ENV lookup. Application::boot() mirrors the real process
// environment into $_ENV (#160), so this works even when variables_order
// leaves $_ENV empty. The 'cache' connection on a non-default database proves
// cache-redis resolves a named connection and honours every setting.
$server = [
    'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
    'password' => ($_ENV['REDIS_PASSWORD'] ?? '') !== '' ? $_ENV['REDIS_PASSWORD'] : null,
    'scheme' => 'tcp',
];

return [
    'connections' => [
        'default' => [...$server, 'database' => 0],
        'cache' => [...$server, 'database' => 2],
    ],
];
