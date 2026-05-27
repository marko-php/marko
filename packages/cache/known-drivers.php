<?php

declare(strict_types=1);

return [
    'marko/cache-file' => 'File-based cache driver (recommended for single-server apps)',
    'marko/cache-redis' => 'Redis cache driver (recommended for distributed deployments and high-throughput apps)',
    'marko/cache-array' => 'In-memory cache driver (request-lifetime only; intended for testing and dev environments)',
];
