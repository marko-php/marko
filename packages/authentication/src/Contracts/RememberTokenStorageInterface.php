<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\Token\RememberTokenRecord;

/**
 * Per-device remember-me tokens (a remember_tokens table or equivalent).
 *
 * When an implementation is bound, SessionGuard issues one token per device
 * instead of the single remember_token column on the user, so a remember-me
 * login on one device no longer logs another out. marko/admin-auth binds a
 * database implementation; without a binding SessionGuard falls back to the
 * user provider's single-column token.
 */
interface RememberTokenStorageInterface
{
    /**
     * Store a newly issued device token.
     */
    public function store(
        RememberTokenRecord $token,
    ): void;

    /**
     * The token the guard issued with this selector, or null when there is none.
     */
    public function findBySelector(
        string $guard,
        string $selector,
    ): ?RememberTokenRecord;

    /**
     * Replace a token's validator hash, keeping its selector and expiry.
     *
     * Only replaces it while the stored hash still equals $currentValidatorHash,
     * so of two requests rotating the same token at once exactly one succeeds.
     *
     * @return bool Whether the token was rotated
     */
    public function rotateValidator(
        string $guard,
        string $selector,
        string $currentValidatorHash,
        string $newValidatorHash,
    ): bool;

    /**
     * Delete one device's token (logout, expiry, a deleted user).
     */
    public function deleteBySelector(
        string $guard,
        string $selector,
    ): void;

    /**
     * Delete every token the guard issued to one user, except the device whose
     * selector is $exceptSelector (logging out other devices).
     *
     * @return int Number of tokens deleted
     */
    public function deleteAllForUser(
        string $guard,
        int|string $userId,
        ?string $exceptSelector = null,
    ): int;

    /**
     * Clear all expired remember tokens from storage.
     *
     * @return int Number of tokens cleared
     */
    public function clearExpiredTokens(): int;

    /**
     * Clear all remember tokens from storage.
     *
     * @return int Number of tokens cleared
     */
    public function clearAllTokens(): int;
}
