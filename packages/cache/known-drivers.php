<?php

declare(strict_types=1);

return [
    'marko/cache-file' => 'File-based cache driver (recommended; no infrastructure, single-server apps)',
    'marko/cache-redis' => 'Redis cache driver (distributed deployments and high-throughput)',
    'marko/cache-array' => 'In-memory cache driver (request-lifetime only; testing and dev)',
];
