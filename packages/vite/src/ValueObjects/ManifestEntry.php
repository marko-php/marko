<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class ManifestEntry
{
    /**
     * @param array<string> $css
     * @param array<string> $imports
     */
    public function __construct(
        public string $name,
        public string $file,
        public ?string $source = null,
        public bool $isEntry = false,
        public array $css = [],
        public array $imports = [],
    ) {}

    public function isCss(): bool
    {
        return str_ends_with($this->file, '.css');
    }
}
