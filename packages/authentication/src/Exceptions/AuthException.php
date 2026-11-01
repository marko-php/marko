<?php

declare(strict_types=1);

namespace Marko\Authentication\Exceptions;

use Exception;
use Throwable;

class AuthException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $context = '',
        private readonly string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    public static function cookieOutsideRequest(
        string $name,
    ): self {
        return new self(
            message: "Cannot queue cookie '$name': no HTTP request is being handled",
            context: 'RequestCookieJar only writes cookies while QueuedCookiesMiddleware is handling a request',
            suggestion: 'Log users in with remember-me (or log them out) from an HTTP request, and make sure marko/authentication\'s QueuedCookiesMiddleware is in the global middleware stack',
        );
    }

    public static function rememberMeUnavailable(
        string $guard,
    ): self {
        return new self(
            message: "Remember-me was requested on guard '$guard', but the guard has no cookie jar or remember token manager",
            context: "SessionGuard '$guard' was constructed without a CookieJarInterface or RememberTokenManager",
            suggestion: 'Resolve the guard through AuthManager (or GuardInterface), or pass cookieJar and tokenManager when constructing SessionGuard yourself',
        );
    }

    public static function rememberTokenNotStored(
        string $guard,
        string $provider,
    ): self {
        return new self(
            message: "Remember-me was requested on guard '$guard', but the user provider did not store the remember token",
            context: "After $provider::updateRememberToken(), the user's getRememberToken() or getRememberTokenExpiresAt() did not return the new token hash and expiry",
            suggestion: 'Make updateRememberToken() call $user->setRememberToken($token) and $user->setRememberTokenExpiresAt($expiresAt) and persist both (e.g. remember_token and remember_token_expires_at columns), or log in without remember: true',
        );
    }

    public static function tokenDriverNotInstalled(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' uses the 'token' driver, but no token guard driver is installed",
            context: "No factory is registered for the 'token' driver in GuardDriverRegistry",
            suggestion: "Install the token guard with 'composer require marko/authentication-token', or register your own 'token' driver with GuardDriverRegistry::extend()",
        );
    }

    /**
     * @param list<string> $configuredGuards
     */
    public static function undefinedGuard(
        string $guard,
        array $configuredGuards,
    ): self {
        $configured = $configuredGuards === [] ? '(none)' : implode(', ', $configuredGuards);

        return new self(
            message: "Guard '$guard' is not defined in authentication.guards",
            context: "AuthManager was asked for guard '$guard'. Configured guards: $configured",
            suggestion: "Add authentication.guards.$guard with a driver, or fix the guard name where it is set "
                . '(authentication.default.guard, authorization.default_guard, or the name passed to AuthManager::guard())',
        );
    }

    public static function missingGuardDriver(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' has no driver",
            context: "authentication.guards.$guard has no 'driver' key",
            suggestion: "Set authentication.guards.$guard.driver, for example 'session' or 'token'",
        );
    }

    /**
     * @param list<string> $availableDrivers
     */
    public static function unknownGuardDriver(
        string $guard,
        string $driver,
        array $availableDrivers,
    ): self {
        $available = implode(', ', $availableDrivers);

        return new self(
            message: "Unknown guard driver '$driver'",
            context: "Guard '$guard' is configured with driver '$driver'. Available drivers: $available",
            suggestion: "Use one of the available drivers in authentication.guards.$guard.driver, or register '$driver' with GuardDriverRegistry::extend() from a module.php boot callback",
        );
    }

    public static function guardNameMismatch(
        string $guard,
        string $driver,
        string $returnedName,
    ): self {
        return new self(
            message: "The '$driver' guard driver returned a guard named '$returnedName' for guard '$guard'",
            context: "AuthManager asked the '$driver' driver factory for guard '$guard'",
            suggestion: 'Pass the $name argument the driver factory receives to the guard it builds, so getName() returns the configured guard name',
        );
    }

    public static function invalidGuardProvider(
        string $guard,
    ): self {
        return new self(
            message: "Guard '$guard' has an invalid provider",
            context: "authentication.guards.$guard.provider must be a non-empty string naming an entry in authentication.providers",
            suggestion: "Set authentication.guards.$guard.provider to a provider name, for example 'users', or remove the key to use the app's UserProviderInterface binding",
        );
    }

    /**
     * @param list<string> $configuredProviders
     */
    public static function undefinedProvider(
        string $guard,
        string $provider,
        array $configuredProviders,
    ): self {
        $configured = $configuredProviders === [] ? '(none)' : implode(', ', $configuredProviders);

        return new self(
            message: "User provider '$provider' is not defined in authentication.providers",
            context: "Guard '$guard' uses provider '$provider'. Configured providers: $configured",
            suggestion: "Add authentication.providers.$provider (with a 'class' implementing UserProviderInterface), or fix authentication.guards.$guard.provider",
        );
    }

    /**
     * @param list<string> $configuredProviders
     */
    public static function undefinedNamedProvider(
        string $provider,
        array $configuredProviders,
    ): self {
        $configured = $configuredProviders === [] ? '(none)' : implode(', ', $configuredProviders);

        return new self(
            message: "User provider '$provider' is not defined in authentication.providers",
            context: "A user provider was requested by name. Configured providers: $configured",
            suggestion: "Add authentication.providers.$provider, or fix the setting that names it (e.g. authentication.password_reset.provider)",
        );
    }

    public static function invalidPositiveSetting(
        string $key,
        int $value,
    ): self {
        return new self(
            message: "$key must be a positive integer, got $value",
            context: "While reading $key from the authentication config",
            suggestion: "Set $key to 1 or more",
        );
    }

    public static function passwordConfirmationNeedsSessionGuard(
        string $guard,
    ): self {
        return new self(
            message: "RequirePasswordConfirmationMiddleware cannot run on stateless guard '$guard'",
            context: 'Password confirmation is stored in the session, and a stateless guard (StatelessGuardInterface) keeps no session between requests',
            suggestion: 'Set authentication.default.guard to a session guard for routes that require password confirmation, or remove RequirePasswordConfirmationMiddleware from API routes',
        );
    }

    public static function corruptPasswordResetToken(
        string $key,
    ): self {
        return new self(
            message: "The cached password reset token record at '$key' is not a valid token record",
            context: "CachePasswordResetTokenRepository expected an array with a string 'token_hash' and an int 'created_at'",
            suggestion: 'Something else wrote to this cache key. Clear the cache entry; the user can request a new reset link',
        );
    }

    public static function invalidProviderClass(
        string $provider,
        string $class,
    ): self {
        return new self(
            message: "User provider '$provider' has an invalid class '$class'",
            context: "authentication.providers.$provider.class must name a class that implements Marko\\Authentication\\Contracts\\UserProviderInterface",
            suggestion: "Point authentication.providers.$provider.class at a UserProviderInterface implementation, or remove the 'class' key to use the app's UserProviderInterface binding",
        );
    }

    public static function throttleRequiresCache(): self
    {
        return new self(
            message: 'Login throttling is enabled, but no cache driver is installed',
            context: 'authentication.throttle.enabled is true, and LoginThrottle counts failed logins in Marko\\Cache\\Contracts\\CacheInterface, which has no binding',
            suggestion: "Install a cache driver ('composer require marko/cache-file', or marko/cache-redis for several servers), or set authentication.throttle.enabled to false and throttle logins yourself",
        );
    }

    public static function invalidThrottleLimit(
        string $name,
        int $value,
    ): self {
        return new self(
            message: "authentication.throttle.$name must be a positive integer, got $value",
            context: 'While building the login throttle from authentication.throttle',
            suggestion: "Set authentication.throttle.$name to 1 or more, or set authentication.throttle.enabled to false to turn login throttling off",
        );
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }
}
