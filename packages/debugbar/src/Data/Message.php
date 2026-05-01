<?php

declare(strict_types=1);

namespace Marko\Debugbar\Data;

readonly class Message
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed>|null $trace
     */
    public function __construct(
        public string $message,
        public string $level,
        public array $context,
        public float $time,
        public ?array $trace = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(float $startTime): array
    {
        return [
            'message' => $this->message,
            'level' => $this->level,
            'context' => $this->context,
            'time_ms' => round(($this->time - $startTime) * 1000, 2),
            'trace' => $this->trace,
        ];
    }
}
