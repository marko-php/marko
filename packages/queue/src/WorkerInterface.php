<?php

declare(strict_types=1);

namespace Marko\Queue;

interface WorkerInterface
{
    /**
     * Process jobs until stopped.
     *
     * Each pass pops the queues in the given order and processes the first job found,
     * so earlier queues are always drained first. The worker sleeps only when every
     * queue is empty. With $once, it processes at most one job across all queues.
     *
     * $options sets the default job timeout and the limits (memory, job count, run time, extra stop
     * conditions) after which the worker returns so a process supervisor can start a fresh one.
     * Stop conditions are checked after every job and after every idle pause.
     *
     * @param list<string>|null $queues Queue names in priority order, or null for the default queue
     * @return WorkerStopReason Why the worker stopped; queue:work exits with its exitCode()
     */
    public function work(
        ?array $queues = null,
        bool $once = false,
        int $sleep = 3,
        WorkerOptions $options = new WorkerOptions(),
    ): WorkerStopReason;

    public function stop(): void;
}
