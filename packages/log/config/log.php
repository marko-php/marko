<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // Handlers that receive every record, in order: 'file' (marko/log-file), 'stream' (marko/log-stream).
    // Several stack, e.g. LOG_HANDLERS=file,stream. Each handler package adds its name to 'handler_map'.
    'handlers' => Env::list('LOG_HANDLERS', ['file']),
    // 'line' (human-readable, the format below) or 'json' (one JSON object per line).
    'formatter' => Env::string('LOG_FORMATTER', 'line'),
    'path' => Env::string('LOG_PATH', 'storage/logs'),
    'level' => Env::string('LOG_LEVEL', 'debug'),
    'channel' => Env::string('LOG_CHANNEL', 'app'),
    'format' => '[{datetime}] {channel}.{level}: {message} {context}',
    'date_format' => 'Y-m-d H:i:s',
    'max_files' => Env::int('LOG_MAX_FILES', 30, min: 0),
    'max_file_size' => Env::int('LOG_MAX_FILE_SIZE', 10 * 1024 * 1024, min: 0),
    'escape_newlines' => true,
    // Context keys (case-insensitive, at any depth) whose values are written as [redacted].
    'redact_keys' => [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'authorization',
    ],
    // Permissions for newly created log files and directories (owner-only by default).
    'file_mode' => 0600,
    'dir_mode' => 0700,
];
