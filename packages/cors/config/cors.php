<?php

declare(strict_types=1);

return [
    'allowed_origins' => array_filter(explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '')),
    'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization'),
    'expose_headers' => array_filter(explode(',', $_ENV['CORS_EXPOSE_HEADERS'] ?? '')),
    'supports_credentials' => filter_var($_ENV['CORS_SUPPORTS_CREDENTIALS'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'max_age' => (int) ($_ENV['CORS_MAX_AGE'] ?? 0),
];
