<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

/**
 * An authentication feature could not save a changed user: no persister, or a user
 * that does not implement the feature interface it needs.
 */
class UserPersisterException extends AuthException
{
    public static function noPersister(
        string $provider,
    ): self {
        return new self(
            message: "No user persister is configured for user provider '$provider'",
            context: "authentication.providers.$provider has no 'persister' key, and Marko\\Authentication\\Contracts\\UserPersisterInterface has no container binding",
            suggestion: "Implement UserPersisterInterface to save your user entity, then bind it in your module.php or set authentication.providers.$provider.persister to its class",
        );
    }

    public static function invalidPersisterClass(
        string $provider,
        string $class,
    ): self {
        return new self(
            message: "User provider '$provider' has an invalid persister class '$class'",
            context: "authentication.providers.$provider.persister must name a class that implements Marko\\Authentication\\Contracts\\UserPersisterInterface",
            suggestion: "Point authentication.providers.$provider.persister at a UserPersisterInterface implementation, or remove the 'persister' key to use the app's UserPersisterInterface binding",
        );
    }

    /**
     * @param list<string> $configuredProviders
     */
    public static function undefinedPersisterProvider(
        string $provider,
        array $configuredProviders,
    ): self {
        $configured = $configuredProviders === [] ? '(none)' : implode(', ', $configuredProviders);

        return new self(
            message: "User provider '$provider' is not defined in authentication.providers",
            context: "Resolving the user persister for provider '$provider'. Configured providers: $configured",
            suggestion: "Add authentication.providers.$provider, or fix the provider name that points at it (e.g. authentication.password_reset.provider)",
        );
    }

    public static function userMissingInterface(
        string $provider,
        string $userClass,
        string $interface,
    ): self {
        return new self(
            message: "User class '$userClass' from user provider '$provider' does not implement $interface",
            context: "An authentication feature needs to change users of provider '$provider' through $interface before persisting them",
            suggestion: "Implement $interface on $userClass, or point the feature at a provider whose users implement it",
        );
    }
}
