<?php

declare(strict_types=1);

namespace Marko\Routing\Attributes;

abstract readonly class Route
{
    public function __construct(
        public string $path,
        public array $middleware = [],
    ) {}

    abstract public function getMethod(): string;
}
