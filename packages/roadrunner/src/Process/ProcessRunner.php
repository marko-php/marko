<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Process;

/**
 * Runs a command with stdin/stdout/stderr inherited directly from the parent
 * PHP process, so RoadRunner's own server log is never captured or swallowed.
 */
readonly class ProcessRunner implements ProcessRunnerInterface
{
    public function run(string $command): int
    {
        $descriptors = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR,
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return 1;
        }

        return proc_close($process);
    }
}
