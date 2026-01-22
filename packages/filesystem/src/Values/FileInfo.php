<?php

declare(strict_types=1);

namespace Marko\Filesystem\Values;

readonly class FileInfo
{
    public function __construct(
        public string $path,
        public int $size,
        public int $lastModified,
        public string $mimeType,
        public bool $isDirectory,
        public string $visibility,
    ) {}

    public function isFile(): bool
    {
        return !$this->isDirectory;
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    public function basename(): string
    {
        return basename($this->path);
    }

    public function extension(): string
    {
        return pathinfo($this->path, PATHINFO_EXTENSION);
    }

    public function directory(): string
    {
        return dirname($this->path);
    }
}
