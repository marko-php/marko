<?php

return [
    'default' => [
        'driver' => 'database',
        'table' => 'sessions',
    ],
    'scopes' => [
        'tenant1' => [
            'driver' => 'redis',
            'table' => 'tenant1_sessions',
        ],
    ],
];
