<?php

declare(strict_types=1);

use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Sync\NullFailedJobRepository;
use Marko\Queue\Sync\SyncQueue;

return [
    'bindings' => [
        QueueInterface::class => SyncQueue::class,
        FailedJobRepositoryInterface::class => NullFailedJobRepository::class,
    ],
];
