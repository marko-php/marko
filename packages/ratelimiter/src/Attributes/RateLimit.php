<?php

declare(strict_types=1);

namespace Marko\RateLimiter\Attributes;

use Attribute;
use Marko\RateLimiter\Exceptions\RateLimitException;

/**
 * Per-route rate limit read by RateLimitMiddleware.
 *
 * A method-level attribute wins over a class-level one. Any limit left null
 * falls back to the ratelimiter config defaults. Routes sharing a name share a
 * counter; without a name, each controller action gets its own counter.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
readonly class RateLimit
{
    /**
     * @throws RateLimitException
     */
    public function __construct(
        public ?int $maxAttempts = null,
        public ?int $decaySeconds = null,
        public ?string $name = null,
    ) {
        if (
            $maxAttempts !== null
            && $maxAttempts < 1
        ) {
            throw RateLimitException::nonPositiveLimit('maxAttempts', $maxAttempts);
        }

        if (
            $decaySeconds !== null
            && $decaySeconds < 1
        ) {
            throw RateLimitException::nonPositiveLimit('decaySeconds', $decaySeconds);
        }

        if ($name === '') {
            throw RateLimitException::emptyName();
        }
    }
}
