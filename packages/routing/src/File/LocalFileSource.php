<?php

declare(strict_types=1);

namespace Marko\Routing\File;

use finfo;
use Marko\Routing\Exceptions\FileResponseException;

/**
 * A file on the local disk.
 *
 * Never build the path from request input with string concatenation: use
 * SafePath::join() so `../` cannot escape the directory you serve from.
 */
readonly class LocalFileSource implements FileSourceInterface
{
    /**
     * @throws FileResponseException When the file is missing, not a regular file or unreadable
     */
    public function __construct(
        private string $path,
        private ResourceChunkReader $resourceChunkReader = new ResourceChunkReader(),
    ) {
        if (!file_exists($path)) {
            throw FileResponseException::fileNotFound($path);
        }

        if (!is_file($path)) {
            throw FileResponseException::notAFile($path);
        }

        if (!is_readable($path)) {
            throw FileResponseException::unreadable($path);
        }
    }

    public function path(): string
    {
        return $this->path;
    }

    public function size(): int
    {
        clearstatcache(true, $this->path);

        return (int) filesize($this->path);
    }

    public function lastModified(): int
    {
        clearstatcache(true, $this->path);

        return (int) filemtime($this->path);
    }

    public function mimeType(): string
    {
        $mimeType = new finfo(FILEINFO_MIME_TYPE)->file($this->path);

        return $mimeType === false ? 'application/octet-stream' : $mimeType;
    }

    public function filename(): string
    {
        return basename($this->path);
    }

    /**
     * @return iterable<string>
     * @throws FileResponseException When the file cannot be opened or read
     */
    public function stream(
        int $offset = 0,
        ?int $length = null,
    ): iterable {
        $handle = @fopen($this->path, 'rb');

        if ($handle === false) {
            throw FileResponseException::cannotOpen($this->path);
        }

        return $this->resourceChunkReader->read($handle, $offset, $length);
    }
}
