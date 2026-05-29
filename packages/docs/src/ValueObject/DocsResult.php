<?php

declare(strict_types=1);

namespace Marko\Docs\ValueObject;

readonly class DocsResult
{
    public function __construct(
        public string $pageId,
        public string $title,
        public string $excerpt,
        public float $score,
    ) {}
}
