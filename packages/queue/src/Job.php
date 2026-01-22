<?php

declare(strict_types=1);

namespace Marko\Queue;

abstract class Job implements JobInterface
{
    private ?string $id = null;

    private int $attempts = 0;

    protected int $maxAttempts = 3;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(
        string $id,
    ): void {
        $this->id = $id;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->attempts++;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
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
