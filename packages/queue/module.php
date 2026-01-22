<?php

declare(strict_types=1);

use Marko\Queue\QueueConfig;

return [
    'enabled' => true,
    'bindings' => [
        QueueConfig::class => QueueConfig::class,
    ],
];
