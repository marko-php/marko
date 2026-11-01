<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class TwoFactorException extends MarkoException
{
    public static function missingIssuer(): self
    {
        return new self(
            message: 'authentication-two-factor.issuer is not set',
            context: 'The issuer is the name authenticator apps show next to the account, and every provisioning URI needs one',
            suggestion: 'Set TWO_FACTOR_ISSUER in your environment, or set authentication-two-factor.issuer in config/authentication-two-factor.php to your app name',
        );
    }

    public static function invalidSetting(
        string $key,
        int $value,
    ): self {
        return new self(
            message: "$key must be a positive integer, got $value",
            context: 'While reading the two-factor configuration',
            suggestion: "Set $key to 1 or more in config/authentication-two-factor.php",
        );
    }

    public static function alreadyEnabled(
        int|string $userId,
    ): self {
        return new self(
            message: "Two-factor authentication is already enabled for user '$userId'",
            context: 'enable() and confirm() only work while two-factor is not confirmed yet, so an active secret is never replaced by accident',
            suggestion: 'Call disable() first to switch to a new authenticator, or regenerateRecoveryCodes() to replace only the recovery codes',
        );
    }

    public static function notEnabled(
        int|string $userId,
    ): self {
        return new self(
            message: "Two-factor authentication is not enabled for user '$userId'",
            context: 'This operation needs a confirmed two-factor secret',
            suggestion: 'Check TwoFactorManagerInterface::isEnabled() first, or let the user finish enable() and confirm()',
        );
    }

    public static function setupNotStarted(
        int|string $userId,
    ): self {
        return new self(
            message: "User '$userId' has no two-factor secret to confirm",
            context: 'confirm() checks a code against the secret enable() generated, and this user has none',
            suggestion: 'Call TwoFactorManagerInterface::enable() before confirm()',
        );
    }

    public static function noPendingTwoFactorChallenge(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' has no pending two-factor challenge",
            context: 'TwoFactorChallenge::verify() was called, but no user on this guard is waiting on the two_factor login challenge',
            suggestion: 'Check TwoFactorChallenge::isPending() before showing the code form, and add TwoFactorLoginChallenge::class to authentication.guards.' . $guard . '.challenges',
        );
    }

    public static function guardNotChallengeable(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' does not support login challenges",
            context: 'Two-factor login needs a guard that implements ChallengeableGuardInterface, such as the session guard',
            suggestion: "Use a 'session' guard for two-factor logins, or pass the right guard name",
        );
    }

    public static function userNotTwoFactorAuthenticatable(
        string $userClass,
    ): self {
        return new self(
            message: "User class '$userClass' does not implement TwoFactorAuthenticatableInterface",
            context: 'Two-factor checks only work for users whose entity stores a two-factor secret',
            suggestion: 'Implement Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface on your user entity',
        );
    }
}
