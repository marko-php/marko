<?php

declare(strict_types=1);

return [
    'host' => $_ENV['PUBSUB_PGSQL_HOST'] ?? '127.0.0.1',
    'port' => (int) ($_ENV['PUBSUB_PGSQL_PORT'] ?? 5432),
    'user' => $_ENV['PUBSUB_PGSQL_USER'] ?? null,
    'password' => $_ENV['PUBSUB_PGSQL_PASSWORD'] ?? null,
    'database' => $_ENV['PUBSUB_PGSQL_DATABASE'] ?? null,
];
