<?php

declare(strict_types=1);

namespace Marko\Auth;

/**
 * Default implementation of AuthenticatableInterface.
 *
 * NOTE: This trait is an exception to the "no traits" rule.
 * Authentication entities (User models) commonly need to implement
 * AuthenticatableInterface. Providing a trait with sensible defaults
 * is a standard pattern in auth libraries and significantly reduces
 * boilerplate for the common case.
 */
trait Authenticatable
{
    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Get the token value for "remember me" session.
     */
    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    /**
     * Set the token value for "remember me" session.
     */
    public function setRememberToken(
        ?string $token,
    ): void {
        $this->rememberToken = $token;
    }

    /**
     * Get the column name for "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
