<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => ArrayCacheDriver::class,
    ],
];
