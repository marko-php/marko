<?php

declare(strict_types=1);

use Marko\Cache\Contracts\LockFactoryInterface;
use Marko\Cache\Lock\LockFactory;

return [
    'bindings' => [
        // LockFactory needs a LockStoreInterface, which a cache driver package
        // binds, and a Marko\Core\Sleep\SleeperInterface, which marko/core binds.
        LockFactoryInterface::class => LockFactory::class,
    ],
];
