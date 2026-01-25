<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\DefaultFilesystem;

return [
    'enabled' => true,
    'bindings' => [
        FilesystemInterface::class => DefaultFilesystem::class,
    ],
];
