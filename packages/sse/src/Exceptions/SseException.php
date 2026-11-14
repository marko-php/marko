<?php

declare(strict_types=1);

namespace Marko\Sse\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/** @noinspection PhpUnused */
class SseException extends MarkoException
{
    public static function ambiguousSource(): self
    {
        return new self(
            message: 'SseStream cannot accept both a dataProvider and a subscription.',
            context: 'Both a dataProvider closure and a Subscription were passed to SseStream.',
            suggestion: 'Provide only one: either a dataProvider for polling or a subscription for real-time messages.',
        );
    }

    public static function noSource(): self
    {
        return new self(
            message: 'SseStream requires either a dataProvider or a subscription.',
            context: 'SseStream was created with neither a dataProvider nor a subscription.',
            suggestion: 'Pass a dataProvider closure or a Subscription instance to SseStream.',
        );
    }

    public static function invalidMaxConnections(
        int $maxConnections,
    ): self {
        return new self(
            message: "sse.max_connections must be at least 1, got $maxConnections.",
            context: 'While building SseConnectionLimiter from config/sse.php',
            suggestion: 'Set SSE_MAX_CONNECTIONS to a positive number, or leave it unset (null) to disable the connection guard.',
        );
    }

    public static function processLocalCache(
        string $cacheClass,
    ): self {
        return new self(
            message: "The SSE connection guard needs a cache shared across PHP processes, but '$cacheClass' keeps data inside one process.",
            context: 'sse.max_connections is set, so open streams are counted in the cache bound to CacheInterface',
            suggestion: 'Install a shared cache driver (marko/cache-redis, or marko/cache-file on a single server), or unset sse.max_connections.',
        );
    }

    public static function invalidField(
        string $field,
        string $value,
    ): self {
        return new self(
            message: "SSE field '$field' must not contain CR, LF or NUL characters.",
            context: "SseEvent was constructed with a '$field' value containing a carriage return, line feed or NUL: " . json_encode(
                $value,
            ),
            suggestion: "Remove all CR (\\r), LF (\\n) and NUL (\\0) characters from the '$field' value before constructing SseEvent.",
        );
    }
}
