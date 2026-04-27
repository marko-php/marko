<?php

declare(strict_types=1);

namespace Marko\Debugbar\Data;

readonly class CapturedLog
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
        public float $time,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(float $requestStart): array
    {
        return [
            'level' => $this->level,
            'message' => $this->message,
            'context' => $this->context,
            'time_ms' => round(($this->time - $requestStart) * 1000, 2),
        ];
    }
}
