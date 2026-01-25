<?php

declare(strict_types=1);

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\DefaultFilesystem;

return [
    'bindings' => [
        FilesystemInterface::class => DefaultFilesystem::class,
    ],
];
