<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => RedisCacheDriver::class,
    ],
];
