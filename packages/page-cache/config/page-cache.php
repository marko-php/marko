<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['PAGE_CACHE_DRIVER'] ?? 'file',
    'path' => $_ENV['PAGE_CACHE_PATH'] ?? 'storage/page-cache',
    'default_ttl' => (int) ($_ENV['PAGE_CACHE_TTL'] ?? 3600),
    'cacheable_status_codes' => [200, 301],
    'cacheable_methods' => ['GET', 'HEAD'],
];
