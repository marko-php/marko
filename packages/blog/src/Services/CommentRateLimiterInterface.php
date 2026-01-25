<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

/**
 * Interface for rate limiting comment submissions.
 *
 * Prevents spam by limiting how frequently the same IP or email
 * can submit comments. Uses cache for storage with automatic TTL cleanup.
 */
interface CommentRateLimiterInterface
{
    /**
     * Check if a comment is allowed from the given IP address.
     */
    public function isAllowed(
        string $ipAddress,
        ?string $email = null,
    ): bool;

    /**
     * Record a comment submission for rate tracking.
     */
    public function recordSubmission(
        string $ipAddress,
        ?string $email = null,
    ): void;

    /**
     * Get seconds remaining until next comment is allowed.
     *
     * Returns 0 if a comment is currently allowed.
     */
    public function getSecondsRemaining(
        string $ipAddress,
        ?string $email = null,
    ): int;
}
