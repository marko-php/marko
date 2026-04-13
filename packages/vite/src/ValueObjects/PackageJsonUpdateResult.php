<?php

declare(strict_types=1);

namespace Marko\Vite\ValueObjects;

readonly class PackageJsonUpdateResult
{
    /**
     * @param array<string> $added
     * @param array<string> $alreadyPresent
     * @param array<string> $updated
     * @param array<string> $skipped
     */
    public function __construct(
        public bool $createdFile = false,
        public array $added = [],
        public array $alreadyPresent = [],
        public array $updated = [],
        public array $skipped = [],
    ) {}

    public function changed(): bool
    {
        return $this->createdFile || $this->added !== [] || $this->updated !== [];
    }
}
