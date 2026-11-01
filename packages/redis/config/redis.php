<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    /*
     * Named Redis connections. Packages such as marko/cache-redis pick one by
     * name in their own config. Add entries (e.g. 'cache', 'queue') to put a
     * consumer on its own database or server.
     */
    'connections' => [
        'default' => [
            'host' => Env::string('REDIS_HOST', '127.0.0.1'),
            'port' => Env::int('REDIS_PORT', 6379, min: 1, max: 65535),
            'password' => Env::nullableString('REDIS_PASSWORD'),
            'database' => Env::int('REDIS_DATABASE', 0, min: 0),
            'scheme' => Env::string('REDIS_SCHEME', 'tcp'),
        ],
    ],
];
