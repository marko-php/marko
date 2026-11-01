<?php

declare(strict_types=1);

use Marko\Log\File\Handler\FileLogHandler;

// Merged into marko/log's config/log.php: makes 'file' a valid name in 'log.handlers'.
return [
    'handler_map' => [
        'file' => FileLogHandler::class,
    ],
];
