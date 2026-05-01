<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class PluginEntry
{
    public function __construct(
        public string $class,
        public string $target,
        public string $method,
        public string $type,
        public int $sortOrder,
    ) {}
}
