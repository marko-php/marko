<?php

declare(strict_types=1);

namespace Marko\Media\Exceptions;

class FileNotFoundException extends MediaException
{
    public static function forPath(
        string $path,
        string $disk,
    ): self {
        return new self(
            message: "File not found at path '$path' on disk '$disk'",
            context: "Attempted to access path: $path on disk: $disk",
            suggestion: "Verify the file exists on the '$disk' disk or check storage configuration",
        );
    }
}
