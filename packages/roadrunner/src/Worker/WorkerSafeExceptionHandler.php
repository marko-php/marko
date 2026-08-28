<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Worker;

use Throwable;

/**
 * Replaces whatever exception handler the booted application installed
 * (e.g. marko/errors-simple's SimpleErrorHandler, which `echo`s its report
 * to STDOUT under a CLI SAPI — exactly what a RoadRunner worker runs under)
 * with one that only ever reaches a PSR logger or STDERR.
 *
 * Must be installed after Application::boot() completes, since module boot
 * callbacks are what register the framework handler in the first place.
 */
readonly class WorkerSafeExceptionHandler
{
    public function __construct(
        private WorkerLogger $logger,
    ) {}

    public function install(): void
    {
        set_exception_handler($this->handle(...));
    }

    public function handle(
        Throwable $throwable,
    ): void {
        $this->logger->error($throwable);
    }
}
