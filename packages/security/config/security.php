<?php

declare(strict_types=1);

return [
    'csrf' => [
        'session_key' => '_csrf_token',
    ],
    'cors' => [
        'allowed_origins' => [],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-CSRF-TOKEN'],
        'max_age' => 86400,
    ],
    'headers' => [
        'x_content_type_options' => 'nosniff',
        'x_frame_options' => 'SAMEORIGIN',
        'x_xss_protection' => '1; mode=block',
        'strict_transport_security' => 'max-age=31536000; includeSubDomains',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => "default-src 'self'",
    ],
];
