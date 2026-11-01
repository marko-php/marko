<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'driver' => 'sync',
    'connection' => 'default',
    'queue' => 'default',
    'retry_after' => 90,

    // Seconds one job may run under queue:work before it is failed and the worker exits; 0 disables it.
    // Needs ext-pcntl, and must stay below retry_after. Override per worker with --timeout.
    'timeout' => 60,
    'max_attempts' => 3,

    // Seconds to wait before retrying a failed job, used when the job sets no $backoff.
    // - int: fixed delay for every retry, e.g. 30
    // - list<int>: delay per attempt, e.g. [10, 60, 300]; the last value repeats
    // - null: exponential curve of 2^attempts * 10 seconds (20, 40, 80, ...)
    'backoff' => null,

    // File queue:restart writes a new token to; every queue:work exits after its current job once the token
    // changes. Relative to the project root. All workers must see the same file (one host or a shared disk).
    'restart_signal' => Env::string('QUEUE_RESTART_SIGNAL', 'storage/queue/restart'),
];
