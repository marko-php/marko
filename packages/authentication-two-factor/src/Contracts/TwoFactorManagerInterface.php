<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Contracts;

use Marko\AuthenticationTwoFactor\TwoFactorSetup;

/**
 * The two-factor lifecycle for one user: enable, confirm, verify, disable.
 *
 * Every change is saved through the UserPersisterInterface of the guard's user
 * provider (marko/authentication's UserPersisterResolver). $guard names that
 * guard and defaults to authentication.default.guard.
 */
interface TwoFactorManagerInterface
{
    /**
     * Start setup: store a new secret and recovery codes. Not active yet, so
     * logins are not challenged until confirm() accepts a first code.
     *
     * @param string $label The account name authenticator apps show, e.g. the user's email
     */
    public function enable(
        TwoFactorAuthenticatableInterface $user,
        string $label,
        ?string $guard = null,
    ): TwoFactorSetup;

    /**
     * Activate two-factor if $code is valid for the secret enable() stored, and
     * end the user's other sessions and remember-me devices on the guard.
     */
    public function confirm(
        TwoFactorAuthenticatableInterface $user,
        string $code,
        ?string $guard = null,
    ): bool;

    /**
     * Turn two-factor off: clear the secret, recovery codes and confirmation.
     */
    public function disable(
        TwoFactorAuthenticatableInterface $user,
        ?string $guard = null,
    ): void;

    /**
     * Replace the recovery codes with new ones, returned in plain text once.
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(
        TwoFactorAuthenticatableInterface $user,
        ?string $guard = null,
    ): array;

    /**
     * Whether the user has a confirmed secret, so logins are challenged.
     */
    public function isEnabled(
        TwoFactorAuthenticatableInterface $user,
    ): bool;

    /**
     * Check a TOTP code or a recovery code for a user with two-factor enabled.
     * An accepted TOTP step is stored (no replay); a recovery code is spent.
     */
    public function verify(
        TwoFactorAuthenticatableInterface $user,
        string $code,
        ?string $guard = null,
    ): bool;
}
