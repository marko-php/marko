<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueue;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobIdGenerator;
use Marko\Queue\QueueConfig;
use Marko\Queue\QueueInterface;
use Marko\Queue\RetryPolicy;
use Psr\Clock\ClockInterface;

return [
    'bindings' => [
        QueueInterface::class => static function (ContainerInterface $container): DatabaseQueue {
            $config = $container->get(QueueConfig::class);

            return new DatabaseQueue(
                connection: $container->get(ConnectionInterface::class),
                jobEnvelope: $container->get(JobEnvelope::class),
                failedJobHandler: $container->get(FailedJobHandlerInterface::class),
                queryBuilderFactory: $container->get(QueryBuilderFactoryInterface::class),
                clock: $container->get(ClockInterface::class),
                databaseTimezoneConfig: $container->get(DatabaseTimezoneConfig::class),
                retryPolicy: $container->get(RetryPolicy::class),
                defaultQueue: $config->queue(),
                retryAfter: $config->retryAfter(),
                jobIdGenerator: $container->get(JobIdGenerator::class),
            );
        },
        FailedJobRepositoryInterface::class => DatabaseFailedJobRepository::class,
    ],
];
