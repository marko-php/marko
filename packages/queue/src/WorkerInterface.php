<?php

declare(strict_types=1);

namespace Marko\Queue;

interface WorkerInterface
{
    public function work(
        ?string $queue = null,
        bool $once = false,
        int $sleep = 3,
    ): void;

    public function stop(): void;
}
