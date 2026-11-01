<?php

declare(strict_types=1);

namespace Marko\Scheduler\Process;

use Marko\Scheduler\Exceptions\SchedulerException;

/**
 * Starts a scheduled console command in a separate process, for runInBackground() tasks.
 */
interface BackgroundCommandLauncherInterface
{
    /**
     * Start the command in a separate process and return at once, without waiting for it.
     *
     * When $mutexName is given, the child process must take that overlap mutex (stale after
     * $mutexExpiresAfterSeconds) before running the command, release it when the command ends,
     * and skip the command while it is held.
     *
     * @param list<string> $arguments Argv-style tokens passed to the command
     * @param int|null $mutexExpiresAfterSeconds Given whenever $mutexName is
     * @throws SchedulerException When the process cannot be started
     */
    public function launch(
        string $command,
        array $arguments,
        ?string $mutexName,
        ?int $mutexExpiresAfterSeconds,
    ): void;
}
