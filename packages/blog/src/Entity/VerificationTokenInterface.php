<?php

declare(strict_types=1);

namespace Marko\Blog\Entity;

interface VerificationTokenInterface
{
    /**
     * Check if the token has expired.
     */
    public function isExpired(): bool;
}
