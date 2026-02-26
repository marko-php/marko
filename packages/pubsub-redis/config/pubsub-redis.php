<?php

declare(strict_types=1);

return [
    'host' => $_ENV['PUBSUB_REDIS_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['PUBSUB_REDIS_PORT'] ?? 6379),
    'password' => $_ENV['PUBSUB_REDIS_PASSWORD'] ?? null,
    'database' => (int) ($_ENV['PUBSUB_REDIS_DATABASE'] ?? 0),
];
