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
     * Validate a submitted token for the current request.
     *
     * CsrfMiddleware checks after the route's own middleware, so a login there
     * may already have rotated the token: accept the token the request arrived
     * with too, until the request ends.
     */
    public function validate(string $token): bool;

    /**
     * Regenerate the CSRF token, replacing the previous one.
     */
    public function regenerate(): string;
}
