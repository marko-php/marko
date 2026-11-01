<?php

declare(strict_types=1);

use Marko\Cache\Contracts\CacheInterface;
use Marko\Cache\Contracts\LockStoreInterface;
use Marko\Cache\Redis\Driver\RedisCacheDriver;
use Marko\Cache\Redis\Lock\RedisLockStore;

return [
    'bindings' => [
        CacheInterface::class => RedisCacheDriver::class,
        LockStoreInterface::class => RedisLockStore::class,
    ],
];
