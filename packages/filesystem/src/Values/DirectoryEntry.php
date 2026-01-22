<?php

declare(strict_types=1);

namespace Marko\Filesystem\Values;

readonly class DirectoryEntry
{
    public function __construct(
        public string $path,
        public bool $isDirectory,
        public int $size,
        public int $lastModified,
    ) {}

    public function isFile(): bool
    {
        return !$this->isDirectory;
    }

    public function basename(): string
    {
        return basename($this->path);
    }
}
