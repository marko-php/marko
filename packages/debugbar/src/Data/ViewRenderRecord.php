<?php

declare(strict_types=1);

namespace Marko\Debugbar\Data;

class ViewRenderRecord
{
    /**
     * @param list<string> $dataKeys
     */
    public function __construct(
        public readonly string $method,
        public readonly string $template,
        public readonly array $dataKeys,
        public readonly float $start,
        public readonly float $durationMs,
        public readonly int $outputSize,
    ) {}
}
