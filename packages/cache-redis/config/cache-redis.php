<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // A connection name from config/redis.php (marko/redis).
    'connection' => 'default',
    'prefix' => Env::string('CACHE_PREFIX', 'marko:cache:'),
];
