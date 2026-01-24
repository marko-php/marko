<?php

declare(strict_types=1);

use Marko\Filesystem\Local\Factory\LocalFilesystemFactory;

return [
    'drivers' => [
        'local' => LocalFilesystemFactory::class,
    ],
];
