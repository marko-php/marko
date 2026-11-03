<?php

declare(strict_types=1);

namespace Marko\Authentication\Contracts;

use Marko\Authentication\PasswordReset\ResetLinkStatus;
use Marko\Authentication\PasswordReset\ResetStatus;

interface PasswordResetBrokerInterface
{
    /**
     * Issue a reset token for the user matching $credentials and hand it to the notifier.
     *
     * Returns Sent whether or not a user matches, so the response does not reveal
     * which emails are registered. Returns Throttled for a repeat request inside
     * authentication.password_reset.throttle_seconds.
     *
     * @param array<string, mixed> $credentials e.g. ['email' => $email]
     */
    public function sendResetLink(
        array $credentials,
    ): ResetLinkStatus;

    /**
     * Read-only: true when $token is the current, unexpired token of the user matching $credentials.
     *
     * Never deletes or touches the token, so a page can check a link before showing the
     * new-password form. An unknown user, a wrong token, a used or replaced token and an
     * expired token all return false, so the result does not reveal which emails are registered.
     * A token this reports valid is one reset() accepts at the same moment.
     *
     * @param array<string, mixed> $credentials e.g. ['email' => $email]
     */
    public function tokenIsValid(
        array $credentials,
        string $token,
    ): bool;

    /**
     * Set a new password for the user matching $credentials if $token is theirs and unexpired.
     *
     * On success the token is deleted, the user is persisted and PasswordResetEvent is dispatched.
     *
     * @param array<string, mixed> $credentials e.g. ['email' => $email]
     */
    public function reset(
        array $credentials,
        string $token,
        string $newPassword,
    ): ResetStatus;
}
