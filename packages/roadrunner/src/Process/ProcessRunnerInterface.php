<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Process;

/**
 * Runs a shell command, streaming its stdout/stderr rather than capturing it.
 */
interface ProcessRunnerInterface
{
    /**
     * Runs the given command to completion and returns its exit code.
     */
    public function run(string $command): int;
}
