<?php

declare(strict_types=1);

namespace Marko\Filesystem\Exceptions;

class PermissionException extends FilesystemException
{
    public static function cannotRead(
        string $path,
    ): self {
        return new self(
            message: "Cannot read file: '$path'",
            context: 'File is not readable',
            suggestion: 'Check file permissions and ensure the web server has read access',
        );
    }

    public static function cannotWrite(
        string $path,
    ): self {
        return new self(
            message: "Cannot write to: '$path'",
            context: 'Directory or file is not writable',
            suggestion: 'Check directory permissions and ensure the web server has write access',
        );
    }

    public static function cannotDelete(
        string $path,
    ): self {
        return new self(
            message: "Cannot delete: '$path'",
            context: 'Insufficient permissions to delete',
            suggestion: 'Check file/directory permissions',
        );
    }
}
