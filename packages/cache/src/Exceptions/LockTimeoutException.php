<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

/**
 * Thrown by LockInterface::block() when an atomic cache lock stays held for
 * the whole wait. Not to be confused with Marko\Database\Exceptions\LockTimeoutException,
 * which is about database row locks.
 */
class LockTimeoutException extends CacheException
{
    public static function forLock(
        string $name,
        int $waitSeconds,
    ): self {
        $unit = $waitSeconds === 1 ? 'second' : 'seconds';

        return new self(
            message: "Timed out after $waitSeconds $unit waiting for lock '$name'",
            context: 'Another owner held the lock for the whole wait of block()',
            suggestion: 'Wait longer, retry later, or check that the current holder releases the lock when its work is done',
        );
    }
}
