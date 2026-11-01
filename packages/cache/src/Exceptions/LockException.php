<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

class LockException extends CacheException
{
    public static function emptyName(): self
    {
        return new self(
            message: 'Lock name cannot be empty',
            context: 'While creating or restoring an atomic lock',
            suggestion: "Name the resource the lock guards, e.g. 'video-pipeline:42'",
        );
    }

    public static function emptyOwner(
        string $name,
    ): self {
        return new self(
            message: "Lock '$name' needs an owner token",
            context: 'While restoring an atomic lock by name and owner',
            suggestion: 'Pass the owner() of the lock that acquired it; it is the only token release() accepts',
        );
    }

    public static function invalidExpiry(
        string $name,
        int $seconds,
    ): self {
        return new self(
            message: "Lock '$name' needs an expiry of at least 1 second, got $seconds",
            context: 'While creating an atomic lock',
            suggestion: 'Every lock expires, so a holder that crashes can never deadlock the app. Pass the longest time the guarded work can take',
        );
    }

    public static function negativeWait(
        string $name,
        int $waitSeconds,
    ): self {
        return new self(
            message: "Lock '$name' cannot wait a negative number of seconds ($waitSeconds)",
            context: 'While calling block() on an atomic lock',
            suggestion: 'Pass 0 to try once, or the number of seconds to keep retrying',
        );
    }

    public static function restoredLockCannotAcquire(
        string $name,
    ): self {
        return new self(
            message: "Lock '$name' was restored and cannot be acquired",
            context: 'A lock rebuilt with LockFactoryInterface::restore() carries no expiry',
            suggestion: 'Use a restored lock only to release() or forceRelease(); call LockFactoryInterface::create() to acquire',
        );
    }
}
