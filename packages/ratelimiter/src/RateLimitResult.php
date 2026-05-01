<?php

declare(strict_types=1);

namespace Marko\RateLimiter;

readonly class RateLimitResult
{
    public function __construct(
        private bool $allowed,
        private int $remaining,
        private ?int $retryAfter = null,
    ) {}

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function remaining(): int
    {
        return $this->remaining;
    }

    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
