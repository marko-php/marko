<?php

declare(strict_types=1);

use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;
use Marko\Queue\Rabbitmq\RabbitmqFailedJobRepository;
use Marko\Queue\Rabbitmq\RabbitmqQueue;

return [
    'bindings' => [
        QueueInterface::class => RabbitmqQueue::class,
        FailedJobRepositoryInterface::class => RabbitmqFailedJobRepository::class,
    ],
];
