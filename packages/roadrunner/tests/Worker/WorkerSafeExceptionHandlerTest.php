<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Worker;

use Marko\Roadrunner\Worker\WorkerLogger;
use Marko\Roadrunner\Worker\WorkerSafeExceptionHandler;
use ReflectionFunction;
use Throwable;

describe('WorkerSafeExceptionHandler', function (): void {
    it('installs a worker safe exception handler over the framework handler', function (): void {
        // Simulate the framework's own handler (e.g. SimpleErrorHandler),
        // already registered by the time the worker installs its own.
        set_exception_handler(fn (Throwable $throwable) => null);

        $handler = new WorkerSafeExceptionHandler(new WorkerLogger());

        try {
            $handler->install();

            $currentlyInstalled = set_exception_handler(fn (Throwable $throwable) => null);
            $reflection = new ReflectionFunction($currentlyInstalled);

            expect($reflection->getClosureThis())->toBe($handler)
                ->and($reflection->getName())->toBe('handle');
        } finally {
            restore_exception_handler();
            restore_exception_handler();
            restore_exception_handler();
        }
    });
});
