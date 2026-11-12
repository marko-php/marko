<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;

return [
    'bindings' => [
        QueueInterface::class => DatabaseQueue::class,
        FailedJobRepositoryInterface::class => DatabaseFailedJobRepository::class,
    ],
    'arguments' => [
        DatabaseQueue::class => [
            'defaultQueue' => fn (ContainerInterface $container): string => $container->get(
                QueueConfig::class,
            )->queue(),
            'retryAfter' => fn (ContainerInterface $container): int => $container->get(
                QueueConfig::class,
            )->retryAfter(),
            'queueRetryAfters' => fn (ContainerInterface $container): array => $container->get(
                QueueConfig::class,
            )->queueRetryAfters(),
        ],
    ],
];
