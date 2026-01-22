<?php

declare(strict_types=1);

namespace Marko\Log\Exceptions;

class LogWriteException extends LogException
{
    public static function forPath(
        string $path,
        string $reason = '',
    ): self {
        return new self(
            message: 'Failed to write to log file',
            context: "Path: $path" . ($reason ? ", Reason: $reason" : ''),
            suggestion: 'Check that the log directory exists and is writable',
        );
    }

    public static function directoryNotWritable(
        string $path,
    ): self {
        return new self(
            message: 'Log directory is not writable',
            context: "Directory: $path",
            suggestion: 'Set appropriate permissions on the log directory (chmod 755 or 775)',
        );
    }
}
