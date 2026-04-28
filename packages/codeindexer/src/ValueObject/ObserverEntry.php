<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class ObserverEntry
{
    public function __construct(
        public string $class,
        public string $event,
        public string $method,
        public int $sortOrder,
    ) {}
}
