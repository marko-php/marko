<?php

declare(strict_types=1);

use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;

return [
    'bindings' => [
        QueueInterface::class => DatabaseQueue::class,
        FailedJobRepositoryInterface::class => DatabaseFailedJobRepository::class,
    ],
];
