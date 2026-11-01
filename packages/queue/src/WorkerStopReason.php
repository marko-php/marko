<?php

declare(strict_types=1);

namespace Marko\Queue;

/**
 * Why a queue worker stopped. Worker::work() returns it, and queue:work exits with its exitCode().
 */
enum WorkerStopReason
{
    /** --once processed one job, or found every queue empty. */
    case Once;

    /** The worker processed --max-jobs jobs. */
    case MaxJobs;

    /** The worker's memory reached the --memory limit. */
    case Memory;

    /** The worker ran for --max-time seconds. */
    case MaxTime;

    /** A restart of every worker was requested. */
    case RestartRequested;

    /** SIGTERM or SIGINT arrived, or stop() was called. */
    case Signal;

    /** A job exceeded its timeout, so the process must be replaced. */
    case TimedOut;

    public function message(): string
    {
        return match ($this) {
            self::Once => 'processed a single pass (--once).',
            self::MaxJobs => 'reached the --max-jobs limit.',
            self::Memory => 'reached the --memory limit.',
            self::MaxTime => 'ran for the --max-time limit.',
            self::RestartRequested => 'a worker restart was requested.',
            self::Signal => 'received a stop signal (SIGTERM/SIGINT) or stop() was called.',
            self::TimedOut => sprintf(
                'a job exceeded its timeout; exiting with status %d so the supervisor starts a fresh worker.',
                Worker::EXIT_TIMED_OUT,
            ),
        };
    }

    /**
     * The process exit status for queue:work: non-zero only when the worker must be replaced after a timeout.
     */
    public function exitCode(): int
    {
        return match ($this) {
            self::TimedOut => Worker::EXIT_TIMED_OUT,
            default => 0,
        };
    }
}
