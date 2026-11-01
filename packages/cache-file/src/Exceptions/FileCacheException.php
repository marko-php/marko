<?php

declare(strict_types=1);

namespace Marko\Cache\File\Exceptions;

use Marko\Cache\Exceptions\CacheException;

class FileCacheException extends CacheException
{
    public static function directoryNotCreatable(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cache directory could not be created: $path",
            context: self::withReason("While creating the file cache directory '$path'", $reason),
            suggestion: "Ensure the parent of '$path' exists and is writable by the web server or CLI user, or change cache.path in config/cache.php",
        );
    }

    public static function writeFailed(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cache entry could not be written: $path",
            context: self::withReason("While writing the cache entry '$path'", $reason),
            suggestion: "Ensure the cache directory is writable by the web server or CLI user and that '$path' is not a directory",
        );
    }

    public static function deleteFailed(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cache entry could not be deleted: $path",
            context: self::withReason("While deleting the cache entry '$path' under its file lock", $reason),
            suggestion: 'Ensure the cache directory is writable by the web server or CLI user',
        );
    }

    public static function openFailed(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cache entry could not be opened: $path",
            context: self::withReason("While opening the cache entry '$path' to update it atomically", $reason),
            suggestion: "Ensure the cache directory is writable by the web server or CLI user, has free space, and that '$path' is not a directory",
        );
    }

    public static function lockFailed(
        string $path,
        ?string $reason = null,
    ): self {
        return new self(
            message: "Cache entry could not be locked: $path",
            context: self::withReason("While locking the cache entry '$path' to update it atomically", $reason),
            suggestion: 'Ensure the cache directory is on a filesystem that supports flock() (local disk rather than some network mounts), or use a cache driver with native atomic increments such as marko/cache-redis',
        );
    }

    private static function withReason(
        string $context,
        ?string $reason,
    ): string {
        return $reason === null || $reason === '' ? $context : "$context: $reason";
    }
}
