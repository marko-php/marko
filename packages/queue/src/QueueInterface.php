<?php

declare(strict_types=1);

namespace Marko\Queue;

interface QueueInterface
{
    public function push(
        JobInterface $job,
        ?string $queue = null,
    ): string;

    public function later(
        int $delay,
        JobInterface $job,
        ?string $queue = null,
    ): string;

    public function pop(?string $queue = null): ?JobInterface;

    public function size(?string $queue = null): int;

    public function clear(?string $queue = null): int;

    public function delete(string $jobId): bool;

    public function release(
        string $jobId,
        int $delay = 0,
    ): bool;
}
