<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Queue\Database\DatabaseFailedJobRepository;
use Marko\Queue\Database\DatabaseQueueFactory;
use Marko\Queue\FailedJobRepositoryInterface;
use Marko\Queue\QueueInterface;

return [
    'enabled' => true,
    'bindings' => [
        QueueInterface::class => function (ContainerInterface $container): QueueInterface {
            return $container->get(DatabaseQueueFactory::class)->create();
        },
        FailedJobRepositoryInterface::class => DatabaseFailedJobRepository::class,
    ],
];
