<?php

declare(strict_types=1);

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PcntlProcessControl;
use Marko\Queue\ProcessControlInterface;
use Marko\Queue\QueueAsyncObserverDispatcher;
use Marko\Queue\RestartSignalFactory;
use Marko\Queue\RestartSignalInterface;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

return [
    'bindings' => [
        AsyncObserverDispatcherInterface::class => QueueAsyncObserverDispatcher::class,
        FailedJobHandlerInterface::class => FailedJobHandler::class,
        JobEnvelope::class => JobEnvelope::class,
        JobRunnerInterface::class => JobRunner::class,
        ProcessControlInterface::class => PcntlProcessControl::class,
        // queue.restart_signal_driver picks the implementation: file or cache
        RestartSignalInterface::class => fn (ContainerInterface $container): RestartSignalInterface => $container
            ->get(RestartSignalFactory::class)
            ->create(),
        WorkerInterface::class => Worker::class,
    ],
];
