<?php

declare(strict_types=1);

namespace Marko\Auth\Contracts;

use Marko\Auth\AuthenticatableInterface;

interface UserProviderInterface
{
    /**
     * Retrieve a user by their unique identifier.
     */
    public function retrieveById(
        int|string $identifier,
    ): ?AuthenticatableInterface;

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array<string, mixed> $credentials
     */
    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface;

    /**
     * Validate a user against the given credentials.
     *
     * @param array<string, mixed> $credentials
     */
    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool;

    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     */
    public function retrieveByRememberToken(
        int|string $identifier,
        string $token,
    ): ?AuthenticatableInterface;

    /**
     * Update the "remember me" token for the given user in storage.
     */
    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
    ): void;
}
