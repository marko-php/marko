<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Contract\IndexCacheInterface;
use Marko\CodeIndexer\Module\ModuleWalker;

return [
    'bindings' => [
        IndexCacheInterface::class => IndexCache::class,
    ],
    'singletons' => [
        IndexCache::class,
        ModuleWalker::class,
    ],
];
