<?php

declare(strict_types=1);

namespace Marko\Routing\File;

/**
 * A file a FileResponse can serve without loading it into memory.
 */
interface FileSourceInterface
{
    /**
     * Size in bytes.
     */
    public function size(): int;

    /**
     * Last modification time as a Unix timestamp.
     */
    public function lastModified(): int;

    public function mimeType(): string;

    /**
     * The file's base name, e.g. for a Content-Disposition header.
     */
    public function filename(): string;

    /**
     * Read the file in chunks, starting at $offset; $length null reads to the end.
     *
     * @return iterable<string>
     */
    public function stream(
        int $offset = 0,
        ?int $length = null,
    ): iterable;
}
