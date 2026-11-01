<?php

declare(strict_types=1);

use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PcntlProcessControl;
use Marko\Queue\ProcessControlInterface;
use Marko\Queue\QueueAsyncObserverDispatcher;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

return [
    'bindings' => [
        AsyncObserverDispatcherInterface::class => QueueAsyncObserverDispatcher::class,
        JobEnvelope::class => JobEnvelope::class,
        JobRunnerInterface::class => JobRunner::class,
        ProcessControlInterface::class => PcntlProcessControl::class,
        WorkerInterface::class => Worker::class,
    ],
];
