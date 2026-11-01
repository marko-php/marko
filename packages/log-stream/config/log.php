<?php

declare(strict_types=1);

use Marko\Config\Env;
use Marko\Log\Stream\Handler\StreamLogHandler;

// Merged into marko/log's config/log.php: makes 'stream' a valid name in 'log.handlers'.
return [
    'handler_map' => [
        'stream' => StreamLogHandler::class,
    ],
    'stream' => [
        // php://stderr or php://stdout. Under RoadRunner only php://stderr is allowed.
        'target' => Env::string('LOG_STREAM_TARGET', 'php://stderr'),
    ],
];
