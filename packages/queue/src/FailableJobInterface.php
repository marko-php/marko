<?php

declare(strict_types=1);

namespace Marko\Queue;

use Throwable;

/**
 * A job that cleans up after itself when it fails for the last time.
 *
 * FailedJobHandler calls failed() once, after the job is stored as failed and before
 * JobFailedEvent is dispatched: on the last attempt, on a timeout, on an invalid backoff,
 * when the database driver finds the job's attempts used up by crashed workers, and when
 * the sync driver runs a job that throws. queue:retry does not call it.
 */
interface FailableJobInterface
{
    /**
     * Undo or flag whatever the job left half-done. Keep it short: on a timeout it runs inside
     * the alarm signal handler just before the worker process exits. A container-aware job has
     * its container while failed() runs, as it does during handle().
     */
    public function failed(Throwable $exception): void;
}
