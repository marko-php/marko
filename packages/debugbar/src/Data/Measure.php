<?php

declare(strict_types=1);

namespace Marko\Debugbar\Data;

readonly class Measure
{
    public function __construct(
        public string $name,
        public float $start,
        public float $end,
    ) {}

    public function durationMs(): float
    {
        return round(($this->end - $this->start) * 1000, 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(float $requestStart): array
    {
        return [
            'name' => $this->name,
            'start_ms' => round(($this->start - $requestStart) * 1000, 2),
            'duration_ms' => $this->durationMs(),
        ];
    }
}
