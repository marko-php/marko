<?php

declare(strict_types=1);

use Marko\Config\RemoveKey;

return [
    'remember' => [
        'cookie' => [
            'secure' => null,
            'path' => '/',
        ],
    ],
    'guards' => [
        'api' => new RemoveKey(),
    ],
];
