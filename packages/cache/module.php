<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Lock\LockFactory;
use Marko\Cache\Lock\SleeperInterface;
use Marko\Cache\Lock\UsleepSleeper;

return [
    'bindings' => [
        // LockFactory needs a LockStoreInterface, which a cache driver package binds.
        LockFactoryInterface::class => LockFactory::class,
        SleeperInterface::class => UsleepSleeper::class,
    ],
];
