<?php

declare(strict_types=1);

namespace Marko\Debugbar\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class DebugbarStorageException extends MarkoException
{
    public static function directoryNotCreatable(
        string $directory,
    ): self {
        return new self(
            message: "Unable to create debugbar storage directory '$directory'",
            context: 'While storing a debugbar profile',
            suggestion: 'Make the parent directory writable by the PHP process, or change debugbar.storage.path',
        );
    }

    public static function fileNotWritable(
        string $file,
    ): self {
        return new self(
            message: "Unable to write debugbar profile '$file'",
            context: 'While storing a debugbar profile',
            suggestion: 'Make the debugbar storage directory writable by the PHP process, or change debugbar.storage.path',
        );
    }
}
