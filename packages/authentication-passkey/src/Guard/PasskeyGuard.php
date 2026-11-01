<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\AuthManager;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnauthenticatedException;
use Marko\Authentication\UserProviderResolver;
use Marko\AuthenticationPasskey\Config\PasskeyConfig;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Exceptions\PasskeyConfigurationException;
use Marko\Config\Exceptions\ConfigNotFoundException;

/**
 * The guard passkeys work with (authentication-passkey.guard) and the user
 * provider its users come from. Registration, sign-in and passkey management
 * all go through here, so they agree on whose passkeys are whose.
 */
readonly class PasskeyGuard
{
    public function __construct(
        private AuthManager $authManager,
        private AuthConfig $authConfig,
        private UserProviderResolver $userProviderResolver,
        private PasskeyConfig $config,
    ) {}

    /**
     * @throws PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function guard(): GuardInterface
    {
        $guard = $this->authManager->guard($this->config->guard());

        if ($guard instanceof StatelessGuardInterface) {
            throw PasskeyConfigurationException::statelessGuard($guard->getName());
        }

        return $guard;
    }

    /**
     * The authentication.providers name the guard's users come from.
     *
     * @throws PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function providerName(): string
    {
        $guardName = $this->config->guard();
        $guardConfig = $this->authConfig->guardConfig($guardName);
        $provider = array_key_exists('provider', $guardConfig)
            ? $guardConfig['provider']
            : $this->authConfig->defaultProviderOrNull();

        if (!is_string($provider) || $provider === '') {
            throw PasskeyConfigurationException::missingProvider($guardName);
        }

        return $provider;
    }

    /**
     * @throws PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function provider(): UserProviderInterface
    {
        return $this->userProviderResolver->forProvider($this->providerName());
    }

    /**
     * The signed-in user.
     *
     * @throws UnauthenticatedException|PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function user(): AuthenticatableInterface
    {
        $guard = $this->guard();

        return $guard->user() ?? throw UnauthenticatedException::forGuard($guard);
    }

    /**
     * Who a ceremony or a passkey belongs to: the provider name and the user id.
     *
     * @throws PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function ownerKey(
        AuthenticatableInterface $user,
    ): string {
        return $this->providerName() . "\0" . $user->getAuthIdentifier();
    }

    /**
     * Whether $credential belongs to $user of the guard's provider.
     *
     * @throws PasskeyConfigurationException|AuthException|ConfigNotFoundException
     */
    public function owns(
        AuthenticatableInterface $user,
        PasskeyCredential $credential,
    ): bool {
        return $credential->provider === $this->providerName()
            && $credential->userId === (string) $user->getAuthIdentifier();
    }
}
