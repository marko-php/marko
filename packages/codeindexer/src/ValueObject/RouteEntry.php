<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class RouteEntry
{
    public function __construct(
        public string $method,
        public string $path,
        public string $class,
        public string $action,
    ) {}
}
