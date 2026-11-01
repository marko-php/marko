<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Memory\Driver\ArrayCacheDriver;
use Marko\Cache\Memory\Lock\ArrayLockStore;

return [
    'bindings' => [
        CacheInterface::class => ArrayCacheDriver::class,
        LockStoreInterface::class => ArrayLockStore::class,
    ],
    'singletons' => [
        // One store per process, so every LockFactory sees the same locks.
        LockStoreInterface::class,
    ],
];
