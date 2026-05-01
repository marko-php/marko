<?php

declare(strict_types=1);

namespace Marko\Debugbar\Data;

readonly class QueryRecord
{
    /**
     * @param array<mixed> $bindings
     */
    public function __construct(
        public string $type,
        public string $sql,
        public array $bindings,
        public float $start,
        public float $durationMs,
        public int $rows,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(
        float $requestStart,
        bool $withBindings,
    ): array {
        return [
            'type' => $this->type,
            'sql' => $this->sql,
            'bindings' => $withBindings ? $this->bindings : [],
            'start_ms' => round(($this->start - $requestStart) * 1000, 2),
            'duration_ms' => $this->durationMs,
            'rows' => $this->rows,
        ];
    }
}
