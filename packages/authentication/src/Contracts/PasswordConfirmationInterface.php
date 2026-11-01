<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\TooManyPasswordConfirmationAttemptsException;

/**
 * Tracks when the user last proved they are present by re-entering their
 * password, so sensitive routes (RequirePasswordConfirmationMiddleware) can ask
 * for it again once that proof is too old.
 *
 * The proof is a timestamp in the session under SESSION_KEY. It is cleared on
 * every login and logout, so it never carries over to another user who signs in
 * on the same session.
 */
interface PasswordConfirmationInterface
{
    public const string SESSION_KEY = 'auth.password_confirmed_at';

    /**
     * Check the user's password and, when it matches, mark the session as
     * confirmed. A wrong password never marks it.
     *
     * Attempts are throttled per user (authentication.password_confirmation.throttle).
     *
     * @throws TooManyPasswordConfirmationAttemptsException|AuthException
     */
    public function confirm(
        AuthenticatableInterface $user,
        string $password,
    ): bool;

    /**
     * Mark the session as confirmed now. confirm() calls this after a correct
     * password; other proofs of presence (a passkey assertion, for example) call it
     * directly.
     */
    public function markConfirmed(): void;

    /**
     * Whether the session was confirmed within
     * authentication.password_confirmation.timeout_seconds.
     *
     * @throws AuthException When the timeout setting is invalid
     */
    public function isRecentlyConfirmed(): bool;
}
