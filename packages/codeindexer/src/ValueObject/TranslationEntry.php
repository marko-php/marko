<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class TranslationEntry
{
    public function __construct(
        public string $key,
        public string $group,
        public string $locale,
        public ?string $namespace,
        public string $file,
        public int $line,
        public string $module,
    ) {}
}
