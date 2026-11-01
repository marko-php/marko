<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * marko/authentication-passkey is misconfigured: a missing or invalid relying
 * party, an origin that does not belong to it, or a guard that cannot hold a
 * passkey login.
 */
class PasskeyConfigurationException extends MarkoException
{
    public static function invalidSetting(
        string $key,
        string $problem,
        mixed $value,
        string $suggestion,
    ): self {
        return new self(
            message: "Configuration key \"authentication-passkey.$key\" $problem",
            context: sprintf('Got %s', var_export($value, true)),
            suggestion: $suggestion,
        );
    }

    public static function statelessGuard(
        string $guard,
    ): self {
        return new self(
            message: "Passkeys need a session guard, but guard '$guard' is stateless",
            context: 'A passkey ceremony keeps its challenge in the session and signs the user in with GuardInterface::login(); a stateless guard (such as the token guard) has neither',
            suggestion: 'Set authentication-passkey.guard to a guard whose driver is "session"',
        );
    }

    public static function missingProvider(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' names no user provider, so passkeys cannot be linked to its users",
            context: "Passkeys are stored with the name of the user provider their owner comes from; authentication.guards.$guard has no 'provider' key and authentication.default.provider is not set",
            suggestion: "Set authentication.guards.$guard.provider (or authentication.default.provider) to a provider name from authentication.providers",
        );
    }
}
