<?php

declare(strict_types=1);

namespace Marko\Queue\Sync;

use Marko\Queue\FailedJob;
use Marko\Queue\FailedJobRepositoryInterface;

class NullFailedJobRepository implements FailedJobRepositoryInterface
{
    public function store(FailedJob $failedJob): void {}

    public function all(): array
    {
        return [];
    }

    public function find(
        string $id,
    ): ?FailedJob {
        return null;
    }

    public function delete(
        string $id,
    ): bool {
        return false;
    }

    public function clear(): int
    {
        return 0;
    }

    public function count(): int
    {
        return 0;
    }
}
