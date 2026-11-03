<?php

declare(strict_types=1);

return [
    'remember' => [
        'cookie' => [
            'secure' => null,
            'path' => '/',
        ],
    ],
    'guards' => [
        'web' => 'session',
        'api' => 'token',
    ],
];
