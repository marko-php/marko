<?php

declare(strict_types=1);

namespace Marko\Session\File\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class SessionWriteException extends MarkoException
{
    public static function partialWrite(
        string $path,
        int $written,
        int $expected,
    ): self {
        return new self(
            message: "Session write failed: wrote $written of $expected bytes",
            context: "Session file: $path",
            suggestion: 'Check available disk space and file system permissions',
        );
    }

    public static function truncateFailed(
        string $path,
    ): self {
        return new self(
            message: 'Session file truncation failed',
            context: "Session file: $path",
            suggestion: 'Check available disk space and file system permissions',
        );
    }
}
