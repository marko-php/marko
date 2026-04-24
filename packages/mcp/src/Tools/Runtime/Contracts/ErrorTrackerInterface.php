<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime\Contracts;

interface ErrorTrackerInterface
{
    /** @return ?array{message: string, file: string, line: int, trace: string, timestamp: int} */
    public function lastError(): ?array;
}
