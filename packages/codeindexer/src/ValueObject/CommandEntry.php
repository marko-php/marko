<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class CommandEntry
{
    public function __construct(
        public string $name,
        public string $class,
        public string $description,
    ) {}
}
