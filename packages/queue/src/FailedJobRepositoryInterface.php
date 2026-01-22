<?php

declare(strict_types=1);

namespace Marko\Queue;

interface FailedJobRepositoryInterface
{
    public function store(FailedJob $failedJob): void;

    public function all(): array;

    public function find(string $id): ?FailedJob;

    public function delete(string $id): bool;

    public function clear(): int;

    public function count(): int;
}
