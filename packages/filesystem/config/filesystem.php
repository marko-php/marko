<?php

declare(strict_types=1);

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'path' => 'storage',
            'public' => false,
        ],
        'public' => [
            'driver' => 'local',
            'path' => 'storage/public',
            'public' => true,
            'url' => '/storage',
        ],
        'temp' => [
            'driver' => 'local',
            'path' => 'storage/temp',
            'public' => false,
        ],
    ],
];
