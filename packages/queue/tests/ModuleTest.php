<?php

declare(strict_types=1);

use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Event\AsyncObserverDispatcherInterface;
use Marko\Core\Path\ProjectPaths;
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
use Marko\Testing\Fake\FakeConfigRepository;

describe('queue module.php', function (): void {
    it('binds RestartSignalInterface to FileRestartSignal by default', function (): void {
        $module = require dirname(__DIR__) . '/module.php';
        $container = new Container();
        $container->instance(ContainerInterface::class, $container);
        $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
            'queue.restart_signal_driver' => 'file',
            'queue.restart_signal' => 'storage/queue/restart',
        ]));
        $container->instance(ProjectPaths::class, new ProjectPaths(sys_get_temp_dir()));

        expect($module['bindings'][RestartSignalInterface::class]($container))
            ->toBeInstanceOf(FileRestartSignal::class);
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
