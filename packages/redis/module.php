<?php

declare(strict_types=1);

use Marko\Redis\RedisConnectionFactory;
use Marko\Redis\RedisConnectionFactoryInterface;

return [
    'bindings' => [
        RedisConnectionFactoryInterface::class => RedisConnectionFactory::class,
    ],
    'singletons' => [
        RedisConnectionFactoryInterface::class,
    ],
];
