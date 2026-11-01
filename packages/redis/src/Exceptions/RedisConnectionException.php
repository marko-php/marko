<?php

declare(strict_types=1);

namespace Marko\Redis\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Throwable;

class RedisConnectionException extends MarkoException
{
    public static function connectionFailed(
        string $host,
        int $port,
        Throwable $previous,
    ): self {
        return new self(
            message: "Could not connect to Redis at $host:$port: {$previous->getMessage()}",
            context: 'Opening a Redis connection from marko/redis.',
            suggestion: 'Check the connection settings in config/redis.php, or set the REDIS_HOST, REDIS_PORT, REDIS_PASSWORD and REDIS_DATABASE environment variables, and make sure the Redis server is reachable.',
            previous: $previous,
        );
    }

    /**
     * @param list<string> $allowed
     */
    public static function invalidScheme(
        string $scheme,
        array $allowed,
    ): self {
        $allowedList = implode(', ', $allowed);

        return new self(
            message: "Invalid Redis connection scheme '$scheme'",
            context: 'Creating a Redis connection from marko/redis.',
            suggestion: "Set 'scheme' for the connection in config/redis.php (or REDIS_SCHEME) to one of: $allowedList. Use tls for any Redis reached over a network you do not control.",
        );
    }

    /**
     * @param list<int|string> $configured
     */
    public static function unknownConnection(
        string $name,
        array $configured,
    ): self {
        $configuredList = $configured === [] ? 'none' : implode(', ', $configured);

        return new self(
            message: "Redis connection '$name' is not configured",
            context: "Resolving a named Redis connection. Configured connections: $configuredList.",
            suggestion: "Add a '$name' entry under 'connections' in config/redis.php, or point the consuming package's 'connection' setting at one of the configured names.",
        );
    }
}
