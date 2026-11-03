<?php

declare(strict_types=1);

namespace Marko\Authentication;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\ChallengeableGuardInterface;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Contracts\LoginThrottleInterface;
use Marko\Authentication\Contracts\OtherDeviceLogoutInterface;
use Marko\Authentication\Contracts\RememberTokenStorageInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Guard\GuardDriverRegistry;
use Marko\Authentication\Guard\SessionGuard;
use Marko\Authentication\Http\CurrentRequest;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Session\Contracts\SessionInterface;
use Override;

/**
 * Builds and caches the configured guards.
 *
 * The guards it builds are not container instances, so a long-running worker
 * reaches them through this shared manager: reset() clears every cached guard
 * that holds per-request state (a session guard's resolved user), keeping the
 * guard instances themselves.
 */
class AuthManager implements ResettableInterface
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    public function __construct(
        private readonly AuthConfig $config,
        private readonly SessionInterface $session,
        private readonly UserProviderResolver $providerResolver,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CookieJarInterface $cookieJar,
        private readonly RememberTokenManager $rememberTokenManager,
        private readonly GuardDriverRegistry $guardDriverRegistry = new GuardDriverRegistry(),
        private readonly ?LoginThrottleInterface $loginThrottle = null,
        private readonly ?RememberTokenStorageInterface $rememberTokenStorage = null,
        private readonly ?CurrentRequest $currentRequest = null,
        private readonly ?UserPersisterResolver $userPersisterResolver = null,
        private readonly ?LoginChallengeResolver $loginChallengeResolver = null,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException
     */
    public function guard(
        ?string $name = null,
    ): GuardInterface {
        $name ??= $this->config->defaultGuard();

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        $guardsConfig = $this->config->guards();

        if (!array_key_exists($name, $guardsConfig)) {
            throw AuthException::undefinedGuard($name, array_map('strval', array_keys($guardsConfig)));
        }

        // Config values are untyped, so check the entry's shape rather than trust it.
        $guardConfig = $guardsConfig[$name];
        $driver = is_array($guardConfig) ? ($guardConfig['driver'] ?? null) : null;

        if (!is_array($guardConfig) || !is_string($driver) || $driver === '') {
            throw AuthException::missingGuardDriver($name);
        }

        $guard = $this->createGuard($driver, $name, $guardConfig);

        $this->guards[$name] = $guard;

        return $guard;
    }

    /**
     * Put a guard instance in place for a guard name, replacing any guard
     * already built for it. guard($name) returns this instance from now on.
     *
     * Used by the marko/testing HTTP test client's actingAs() to authenticate
     * a user without a login request.
     */
    public function useGuard(
        string $name,
        GuardInterface $guard,
    ): void {
        $this->guards[$name] = $guard;
    }

    /**
     * Build a guard: a driver registered in GuardDriverRegistry wins, then
     * the built-in session driver. Anything else fails loudly. Each guard
     * gets the user provider its config names (UserProviderResolver).
     *
     * A guard listing login challenges (authentication.guards.<name>.challenges)
     * must be built by a driver whose guards implement ChallengeableGuardInterface;
     * the built-in session guard receives the resolved challenges.
     *
     * @param array<string, mixed> $guardConfig
     * @throws AuthException|ConfigNotFoundException
     */
    private function createGuard(
        string $driver,
        string $name,
        array $guardConfig,
    ): GuardInterface {
        $provider = $this->providerResolver->forGuard($name, $guardConfig);
        $guard = $this->guardDriverRegistry->create($driver, $name, $guardConfig, $provider);

        if ($guard !== null) {
            if ($guard->getName() !== $name) {
                throw AuthException::guardNameMismatch($name, $driver, $guard->getName());
            }

            if (!empty($guardConfig['challenges']) && !$guard instanceof ChallengeableGuardInterface) {
                throw AuthException::challengesNotSupported($name, $driver);
            }

            return $guard;
        }

        if ($driver === 'session') {
            return $this->createSessionGuard(
                $name,
                $guardConfig,
                $provider,
                $this->challengesFor($name, $guardConfig),
            );
        }

        if ($driver === 'token') {
            throw AuthException::tokenDriverNotInstalled($name);
        }

        throw AuthException::unknownGuardDriver(
            $name,
            $driver,
            array_values(array_unique(['session', ...$this->guardDriverRegistry->drivers()])),
        );
    }

    /**
     * @param array<string, mixed> $guardConfig
     * @return list<LoginChallengeInterface>
     * @throws AuthException
     */
    private function challengesFor(
        string $name,
        array $guardConfig,
    ): array {
        if (empty($guardConfig['challenges'])) {
            return [];
        }

        if ($this->loginChallengeResolver === null) {
            throw AuthException::challengeResolverMissing($name);
        }

        return $this->loginChallengeResolver->forGuard($name, $guardConfig);
    }

    /**
     * @param array<string, mixed> $guardConfig
     * @param list<LoginChallengeInterface> $challenges
     * @throws AuthException|ConfigNotFoundException
     */
    private function createSessionGuard(
        string $name,
        array $guardConfig,
        UserProviderInterface $provider,
        array $challenges = [],
    ): SessionGuard {
        return new SessionGuard(
            session: $this->session,
            provider: $provider,
            name: $name,
            cookieJar: $this->cookieJar,
            tokenManager: $this->rememberTokenManager,
            eventDispatcher: $this->eventDispatcher,
            rememberCookiePrefix: $this->config->rememberCookiePrefix(),
            loginThrottle: $this->loginThrottle,
            rememberTokenStorage: $this->rememberTokenStorage,
            currentRequest: $this->currentRequest,
            userPersisterResolver: $this->userPersisterResolver,
            providerName: $this->config->guardProviderName($name, $guardConfig),
            challenges: $challenges,
        );
    }

    /**
     * @throws AuthException
     */
    public function check(): bool
    {
        return $this->guard()->check();
    }

    /**
     * @throws AuthException
     */
    public function user(): ?AuthenticatableInterface
    {
        return $this->guard()->user();
    }

    /**
     * @throws AuthException
     */
    public function id(): int|string|null
    {
        return $this->guard()->id();
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array<string, mixed> $credentials
     * @param bool $remember Keep the user signed in with a remember-me cookie once logged in
     * @throws AuthException
     */
    public function attempt(
        array $credentials,
        bool $remember = false,
    ): bool {
        return $this->guard()->attempt($credentials, $remember);
    }

    /**
     * @throws AuthException
     */
    public function logout(): void
    {
        $this->guard()->logout();
    }

    /**
     * End the user's other sessions and remember-me devices on a guard (default: the
     * default guard), keeping the current session and remember token. Typically called
     * after the user changed or reset their password.
     *
     * @throws AuthException|ConfigNotFoundException
     */
    public function logoutOtherDevices(
        AuthenticatableInterface $user,
        ?string $guard = null,
    ): void {
        $guardInstance = $this->guard($guard);

        if (!$guardInstance instanceof OtherDeviceLogoutInterface) {
            throw AuthException::otherDeviceLogoutUnsupported($guardInstance->getName(), $guardInstance::class);
        }

        $guardInstance->logoutOtherDevices($user);
    }

    /**
     * Clear the per-request state of every guard built or registered so far.
     * Guards that hold none (for example a test FakeGuard) are left alone.
     */
    #[Override]
    public function reset(): void
    {
        foreach ($this->guards as $guard) {
            if ($guard instanceof ResettableInterface) {
                $guard->reset();
            }
        }
    }
}
