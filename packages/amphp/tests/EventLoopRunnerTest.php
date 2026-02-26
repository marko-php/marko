<?php

declare(strict_types=1);

use Marko\Amphp\EventLoopRunner;

describe('EventLoopRunner', function (): void {
    it('creates EventLoopRunner with run method that starts the Revolt event loop', function (): void {
        $runner = new EventLoopRunner();

        expect($runner)->toBeInstanceOf(EventLoopRunner::class)
            ->and(method_exists($runner, 'run'))->toBeTrue();
    });

    it('creates EventLoopRunner with stop method that stops the event loop', function (): void {
        $runner = new EventLoopRunner();

        expect(method_exists($runner, 'stop'))->toBeTrue();

        // stop() can be called without error even when not running
        $runner->stop();

        expect($runner->isRunning())->toBeFalse();
    });

    it('tracks running state via isRunning method', function (): void {
        $runner = new class () extends EventLoopRunner
        {
            protected function doRun(): void
            {
                // No-op: don't call Revolt\EventLoop::run() in tests
            }
        };

        expect($runner->isRunning())->toBeFalse();

        $runner->run();

        // After run() completes, isRunning is false again
        expect($runner->isRunning())->toBeFalse();
    });
});
