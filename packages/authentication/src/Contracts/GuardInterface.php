<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;

interface GuardInterface
{
    /**
     * Check if a user is authenticated.
     */
    public function check(): bool;

    /**
     * Check if the current user is a guest (not authenticated).
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user.
     */
    public function user(): ?AuthenticatableInterface;

    /**
     * Get the ID of the currently authenticated user.
     */
    public function id(): int|string|null;

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array<string, mixed> $credentials
     */
    public function attempt(
        array $credentials,
    ): bool;

    /**
     * Log a user into the application.
     */
    public function login(
        AuthenticatableInterface $user,
    ): void;

    /**
     * Log a user into the application by their ID.
     */
    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface;

    /**
     * Log the user out of the application.
     */
    public function logout(): void;

    public UserProviderInterface $provider {
        set;
    }

    /**
     * Get the unique name of the guard.
     */
    public function getName(): string;
}
