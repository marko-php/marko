<?php

declare(strict_types=1);

use Marko\Cache\Config\CacheConfig;

return [
    'enabled' => true,
    'bindings' => [
        CacheConfig::class => CacheConfig::class,
    ],
];
