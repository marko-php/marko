<?php

declare(strict_types=1);

use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Queue\FailedJobHandler;
use Marko\Queue\FailedJobHandlerInterface;
use Marko\Queue\FileRestartSignal;
use Marko\Queue\JobEnvelope;
use Marko\Queue\JobRunner;
use Marko\Queue\JobRunnerInterface;
use Marko\Queue\PcntlProcessControl;
use Marko\Queue\ProcessControlInterface;
use Marko\Queue\QueueAsyncObserverDispatcher;
use Marko\Queue\RestartSignalInterface;
use Marko\Queue\Worker;
use Marko\Queue\WorkerInterface;

describe('queue module.php', function (): void {
    it('binds RestartSignalInterface to FileRestartSignal', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][RestartSignalInterface::class])->toBe(FileRestartSignal::class);
    });

    it('binds WorkerInterface to Worker in module.php', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'])->toHaveKey(WorkerInterface::class)
            ->and($module['bindings'][WorkerInterface::class])->toBe(Worker::class);
    });

    it('binds JobEnvelope to itself', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][JobEnvelope::class])->toBe(JobEnvelope::class);
    });

    it('binds FailedJobHandlerInterface to FailedJobHandler', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][FailedJobHandlerInterface::class])->toBe(FailedJobHandler::class);
    });

    it('binds JobRunnerInterface to JobRunner', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][JobRunnerInterface::class])->toBe(JobRunner::class);
    });

    it('binds AsyncObserverDispatcherInterface to QueueAsyncObserverDispatcher in module.php', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][AsyncObserverDispatcherInterface::class])
            ->toBe(QueueAsyncObserverDispatcher::class);
    });

    it('binds ProcessControlInterface to PcntlProcessControl', function (): void {
        $module = require dirname(__DIR__) . '/module.php';

        expect($module['bindings'][ProcessControlInterface::class])->toBe(PcntlProcessControl::class);
    });
});
