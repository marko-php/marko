<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Contracts\FilesystemManagerInterface;
use Marko\Filesystem\DefaultFilesystem;
use Marko\Filesystem\Manager\FilesystemManager;

return [
    'bindings' => [
        FilesystemInterface::class => DefaultFilesystem::class,
        FilesystemManagerInterface::class => FilesystemManager::class,
    ],
];
