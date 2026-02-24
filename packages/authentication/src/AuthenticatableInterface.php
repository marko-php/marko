<?php

declare(strict_types=1);

namespace Marko\Authentication;

interface AuthenticatableInterface
{
    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): int|string;

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string;

    /**
     * Get the token value for "remember me" session.
     */
    public function getRememberToken(): ?string;

    /**
     * Set the token value for "remember me" session.
     */
    public function setRememberToken(
        ?string $token,
    ): void;

    /**
     * Get the column name for "remember me" token.
     */
    public function getRememberTokenName(): string;
}
