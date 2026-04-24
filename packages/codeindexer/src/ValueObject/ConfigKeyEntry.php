<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class ConfigKeyEntry
{
    public function __construct(
        public string $key,
        public string $type,
        public mixed $defaultValue,
        public string $module,
        public string $file = '',
        public int $line = 0,
    ) {}
}
