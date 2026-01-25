<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

/**
 * Interface for CSRF token validation.
 *
 * This interface is optional - if no implementation is registered,
 * the CommentController will skip CSRF validation.
 */
interface CsrfValidatorInterface
{
    /**
     * Validate a CSRF token.
     *
     * @param string $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public function validate(
        string $token,
    ): bool;

    /**
     * Generate a new CSRF token.
     *
     * @return string The generated token
     */
    public function generate(): string;
}
