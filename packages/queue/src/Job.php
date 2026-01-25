<?php

declare(strict_types=1);

namespace Marko\Queue;

abstract class Job implements JobInterface
{
    public private(set) ?string $id = null;

    public private(set) int $attempts = 0;

    public protected(set) int $maxAttempts = 3;

    public function setId(
        string $id,
    ): void {
        $this->id = $id;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function serialize(): string
    {
        return serialize($this);
    }

    public static function unserialize(
        string $data,
    ): static {
        return unserialize($data);
    }
}
