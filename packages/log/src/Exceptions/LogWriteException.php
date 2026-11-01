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
            suggestion: 'Make the log directory writable by the PHP process user (e.g. chown it to that user and chmod 700)',
        );
    }

    public static function forStream(
        string $stream,
        string $reason = '',
    ): self {
        return new self(
            message: 'Failed to write to log stream',
            context: "Stream: $stream" . ($reason ? ", Reason: $reason" : ''),
            suggestion: 'Check that the stream is open and its reader is still running (a closed pipe or a stopped log collector fails every write)',
        );
    }
}
