<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class IndexCacheException extends MarkoException
{
    public static function cacheDirUnwritable(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cannot write to index cache: $path",
            context: self::withReason('While building or saving the codeindexer cache', $reason),
            suggestion: 'Ensure the .marko directory is writable, or remove a stale cache file',
        );
    }

    public static function cacheNotRemovable(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cannot remove index cache: $path",
            context: self::withReason('While invalidating the codeindexer cache', $reason),
            suggestion: 'Ensure the .marko directory is writable by the current user, or delete the cache file manually',
        );
    }

    private static function withReason(
        string $context,
        ?string $reason,
    ): string {
        return ($reason === null || $reason === '')
            ? $context
            : "$context: $reason";
    }
}
