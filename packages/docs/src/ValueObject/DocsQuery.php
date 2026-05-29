<?php

declare(strict_types=1);

namespace Marko\Docs\ValueObject;

readonly class DocsQuery
{
    public function __construct(
        public string $query,
        public int $limit = 10,
    ) {}
}
