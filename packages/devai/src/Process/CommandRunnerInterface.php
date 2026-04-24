<?php

declare(strict_types=1);

namespace Marko\DevAi\Process;

interface CommandRunnerInterface
{
    /**
     * @param list<string> $args
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(string $command, array $args = []): array;

    public function isOnPath(string $binary): bool;
}
