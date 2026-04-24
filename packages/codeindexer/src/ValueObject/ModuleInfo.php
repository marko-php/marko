<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class ModuleInfo
{
    /** @param array<string, mixed> $manifest */
    public function __construct(
        public string $name,
        public string $path,
        public string $namespace,
        public array $manifest = [],
    ) {}
}
