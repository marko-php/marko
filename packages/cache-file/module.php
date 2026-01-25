<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\File\Driver\FileCacheDriver;

return [
    'bindings' => [
        CacheInterface::class => FileCacheDriver::class,
    ],
];
