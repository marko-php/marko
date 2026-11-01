<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\File\Driver\FileCacheDriver;
use Marko\Cache\File\Lock\FileLockStore;

return [
    'bindings' => [
        CacheInterface::class => FileCacheDriver::class,
        LockStoreInterface::class => FileLockStore::class,
    ],
];
