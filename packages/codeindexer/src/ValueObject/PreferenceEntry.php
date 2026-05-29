<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\ValueObject;

readonly class PreferenceEntry
{
    public function __construct(
        public string $interface,
        public string $implementation,
        public string $module,
    ) {}
}
