<?php

declare(strict_types=1);

return [
    // The queue worker logs through marko/log; keep its log files inside this temporary project.
    'path' => dirname(__DIR__) . '/storage/logs',
];
