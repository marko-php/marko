<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Contracts;

interface ConsoleDispatcherInterface
{
    /** @return array{exitCode: int, stdout: string, stderr: string} */
    public function dispatch(string $command, array $args = []): array;
}
