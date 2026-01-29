<?php

declare(strict_types=1);

return [
    'driver' => 'sync',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,
    'max_attempts' => 3,
];
