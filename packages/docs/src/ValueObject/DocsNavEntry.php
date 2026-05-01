<?php

declare(strict_types=1);

namespace Marko\Docs\ValueObject;

readonly class DocsNavEntry
{
    public function __construct(
        public string $id,
        public string $title,
        public string $path,
        public int $depth = 0,
    ) {}
}
