<?php

declare(strict_types=1);

namespace Marko\Routing\File;

use Marko\Filesystem\Contracts\FilesystemInterface;
use Marko\Filesystem\Exceptions\FilesystemException;

/**
 * A file on a marko/filesystem disk (local, S3, ...), streamed through readStream().
 *
 * Needs marko/filesystem, which marko/routing only suggests:
 * `composer require marko/filesystem` plus a driver such as marko/filesystem-local.
 */
readonly class FilesystemFileSource implements FileSourceInterface
{
    public function __construct(
        private FilesystemInterface $filesystem,
        private string $path,
        private ResourceChunkReader $resourceChunkReader = new ResourceChunkReader(),
    ) {}

    /**
     * @throws FilesystemException
     */
    public function size(): int
    {
        return $this->filesystem->size($this->path);
    }

    /**
     * @throws FilesystemException
     */
    public function lastModified(): int
    {
        return $this->filesystem->lastModified($this->path);
    }

    /**
     * @throws FilesystemException
     */
    public function mimeType(): string
    {
        return $this->filesystem->mimeType($this->path);
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    /**
     * @return iterable<string>
     * @throws FilesystemException
     */
    public function stream(
        int $offset = 0,
        ?int $length = null,
    ): iterable {
        return $this->resourceChunkReader->read($this->filesystem->readStream($this->path), $offset, $length);
    }
}
