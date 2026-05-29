<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Contracts;

use Marko\RateLimiter\RateLimitResult;

interface RateLimiterInterface
{
    /**
     * Attempt to perform an action, incrementing the attempt count.
     *
     * @param string $key         The rate limit key (e.g., client IP)
     * @param int    $maxAttempts Maximum number of attempts allowed
     * @param int    $decaySeconds Time window in seconds before attempts reset
     */
    public function attempt(
        string $key,
        int $maxAttempts,
        int $decaySeconds,
    ): RateLimitResult;

    /**
     * Check if the key has exceeded the maximum attempts without incrementing.
     *
     * @param string $key         The rate limit key
     * @param int    $maxAttempts Maximum number of attempts allowed
     */
    public function tooManyAttempts(
        string $key,
        int $maxAttempts,
    ): bool;

    /**
     * Clear the rate limit for a given key.
     *
     * @param string $key The rate limit key to clear
     */
    public function clear(
        string $key,
    ): void;
}
