<?php

declare(strict_types=1);

use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Redis\RedisFailedJobRepository;
use Marko\Queue\Redis\RedisQueue;

return [
    'bindings' => [
        QueueInterface::class => RedisQueue::class,
        FailedJobRepositoryInterface::class => RedisFailedJobRepository::class,
    ],
];
