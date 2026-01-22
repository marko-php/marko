<?php

declare(strict_types=1);

use Marko\Filesystem\Config\FilesystemConfig;
use Marko\Filesystem\Manager\FilesystemManager;

return [
    'enabled' => true,
    'bindings' => [
        FilesystemConfig::class => FilesystemConfig::class,
        FilesystemManager::class => FilesystemManager::class,
    ],
];
