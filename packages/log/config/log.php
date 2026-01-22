<?php

declare(strict_types=1);

return [
    'driver' => $_ENV['LOG_DRIVER'] ?? 'file',
    'path' => $_ENV['LOG_PATH'] ?? 'storage/logs',
    'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
    'channel' => $_ENV['LOG_CHANNEL'] ?? 'app',
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => (int) ($_ENV['LOG_MAX_FILES'] ?? 30),
    'max_file_size' => (int) ($_ENV['LOG_MAX_FILE_SIZE'] ?? 10 * 1024 * 1024),
];
