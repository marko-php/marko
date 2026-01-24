<?php

declare(strict_types=1);

namespace Marko\Cache\Exceptions;

class InvalidKeyException extends CacheException
{
    private const array INVALID_CHARS = ['/', '\\', ':', '*', '?', '"', '<', '>', '|', '{', '}'];

    public static function forKey(
        string $key,
    ): self {
        $invalidChars = implode(' ', self::INVALID_CHARS);

        return new self(
            message: "Invalid cache key: '$key'",
            context: 'Key contains invalid characters',
            suggestion: "Cache keys cannot contain: $invalidChars",
        );
    }

    public static function emptyKey(): self
    {
        return new self(
            message: 'Cache key cannot be empty',
            context: 'An empty string was provided as cache key',
            suggestion: 'Provide a non-empty string as the cache key',
        );
    }

    /**
     * Check if a key is valid.
     */
    public static function isValidKey(
        string $key,
    ): bool {
        if ($key === '') {
            return false;
        }

        return array_all(self::INVALID_CHARS, fn ($char) => !str_contains($key, $char));
    }
}
