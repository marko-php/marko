<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

class ItemNotFoundException extends CacheException
{
    public static function forKey(
        string $key,
    ): self {
        return new self(
            message: "Cache item not found: '$key'",
            context: "Requested key: $key",
            suggestion: 'Use CacheInterface::has() to check if an item exists before accessing it',
        );
    }
}
