<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class IndexCacheException extends MarkoException
{
    public static function cacheDirUnwritable(string $path): self
    {
        return new self(
            message: "Cannot write to index cache: $path",
            context: 'While building or saving the codeindexer cache',
            suggestion: 'Ensure the .marko directory is writable, or remove a stale cache file',
        );
    }
}
