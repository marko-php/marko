<?php

declare(strict_types=1);

namespace Marko\Filesystem\Exceptions;

class FileNotFoundException extends FilesystemException
{
    public static function forPath(
        string $path,
    ): self {
        return new self(
            message: "File not found: '$path'",
            context: "Requested path: $path",
            suggestion: 'Verify the file exists and the path is correct',
        );
    }
}
