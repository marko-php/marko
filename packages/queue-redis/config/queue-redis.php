<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    // A connection name from config/redis.php (marko/redis).
    'connection' => 'default',

    // Prepended to every key the driver writes: {prefix}queues:{name}, {prefix}jobs:{id}, {prefix}failed_jobs.
    'prefix' => Env::string('QUEUE_REDIS_PREFIX', 'marko:'),

    // Seconds a worker with a single queue waits on Redis for a job to arrive before polling again; 0 disables it.
    // Keep it below the connection's read timeout (PHP's default_socket_timeout, 60 seconds by default).
    'block_for' => Env::int('QUEUE_REDIS_BLOCK_FOR', 0, min: 0),
];
