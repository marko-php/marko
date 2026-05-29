<?php

declare(strict_types=1);

namespace Marko\DevServer\Process;

readonly class ProcessEntry
{
    public function __construct(
        public string $name,
        public int $pid,
        public string $command,
        public int $port,
        public string $startedAt,
    ) {}
}
