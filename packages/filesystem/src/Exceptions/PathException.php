<?php

declare(strict_types=1);

namespace Marko\Filesystem\Exceptions;

class PathException extends FilesystemException
{
    public static function traversalAttempt(
        string $path,
    ): self {
        return new self(
            message: 'Path traversal attempt detected',
            context: "Attempted path: $path",
            suggestion: "Use paths relative to the disk root without '..' sequences",
        );
    }

    public static function invalidPath(
        string $path,
        string $reason,
    ): self {
        return new self(
            message: "Invalid path: '$path'",
            context: $reason,
            suggestion: 'Provide a valid filesystem path',
        );
    }
}
