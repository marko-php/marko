<?php

declare(strict_types=1);

namespace Marko\Health\Value;

readonly class HealthResult
{
    public function __construct(
        public string $name,
        public HealthStatus $status,
        public string $message,
        public array $metadata,
        public float $duration,
    ) {}

    public function isHealthy(): bool
    {
        return $this->status === HealthStatus::Healthy;
    }

    public function isDegraded(): bool
    {
        return $this->status === HealthStatus::Degraded;
    }

    public function isUnhealthy(): bool
    {
        return $this->status === HealthStatus::Unhealthy;
    }
}
