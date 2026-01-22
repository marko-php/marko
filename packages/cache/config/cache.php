<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
    'path' => $_ENV['CACHE_PATH'] ?? 'storage/cache',
    'default_ttl' => (int) ($_ENV['CACHE_TTL'] ?? 3600),
];
