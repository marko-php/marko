<?php

declare(strict_types=1);

namespace Marko\Security\Contracts;

interface CsrfTokenManagerInterface
{
    /**
     * Get the current CSRF token, generating one if none exists.
     */
    public function get(): string;

    /**
     * Validate a submitted token against the stored token.
     */
    public function validate(string $token): bool;

    /**
     * Regenerate the CSRF token, replacing the previous one.
     */
    public function regenerate(): string;
}
