<?php

declare(strict_types=1);

use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\PageCache\File\Driver\FilePageCacheDriver;

return [
    'bindings' => [
        PageCacheInterface::class => FilePageCacheDriver::class,
    ],
];
