<?php

declare(strict_types=1);

return [
    'driver' => 'file',
    'lifetime' => 120, // minutes
    'expire_on_close' => false,
    'path' => 'storage/sessions',

    // Cookie configuration
    'cookie' => [
        'name' => 'marko_session',
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'lax',
    ],

    // Garbage collection
    'gc_probability' => 2,
    'gc_divisor' => 100,
];
