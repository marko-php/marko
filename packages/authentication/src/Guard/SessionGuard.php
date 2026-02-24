<?php

declare(strict_types=1);

namespace Marko\Authentication\Guard;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\CookieJarInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Authentication\Event\FailedLoginEvent;
use Marko\Authentication\Event\LoginEvent;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Token\RememberTokenManager;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Session\Contracts\SessionInterface;
use Random\RandomException;

class SessionGuard implements GuardInterface
{
    private const int REMEMBER_COOKIE_MINUTES = 43200; // 30 days

    private ?AuthenticatableInterface $cachedUser = null;

    public function __construct(
        private readonly SessionInterface $session,
        public UserProviderInterface $provider {
            set {
                $this->provider = $value;
            }
        },
        private readonly string $name = 'session',
        private readonly ?CookieJarInterface $cookieJar = null,
        private readonly ?RememberTokenManager $tokenManager = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @throws RandomException
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @throws RandomException
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * @throws RandomException
     */
    public function user(): ?AuthenticatableInterface
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get($this->getSessionKey());

        if ($id !== null) {
            $this->cachedUser = $this->provider->retrieveById($id);

            return $this->cachedUser;
        }

        // Try to authenticate via remember token
        $this->cachedUser = $this->authenticateViaRememberCookie();

        return $this->cachedUser;
    }

    /**
     * @throws RandomException
     */
    private function authenticateViaRememberCookie(): ?AuthenticatableInterface
    {
        if ($this->cookieJar === null || $this->tokenManager === null) {
            return null;
        }

        $cookieValue = $this->cookieJar->get($this->getRememberCookieName());

        if ($cookieValue === null) {
            return null;
        }

        $parts = explode('|', $cookieValue, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$id, $token] = $parts;

        $user = $this->provider->retrieveByRememberToken($id, $token);

        if ($user === null) {
            return null;
        }

        // Validate the token
        $storedHash = $user->getRememberToken();

        if ($storedHash === null || !$this->tokenManager->validate($token, $storedHash)) {
            return null;
        }

        // Regenerate the token for security (prevents replay attacks)
        $this->createRememberToken($user);

        return $user;
    }

    /**
     * @throws RandomException
     */
    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    /**
     * @throws AuthException|RandomException
     */
    public function attempt(
        array $credentials,
    ): bool {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            $this->dispatchFailedLoginEvent($credentials);

            return false;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            $this->dispatchFailedLoginEvent($credentials);

            return false;
        }

        $this->login($user);

        return true;
    }

    private function dispatchFailedLoginEvent(
        array $credentials,
    ): void {
        $this->eventDispatcher?->dispatch(new FailedLoginEvent(
            credentials: $credentials,
            guard: $this->name,
        ));
    }

    /**
     * @throws AuthException|RandomException
     */
    public function login(
        AuthenticatableInterface $user,
        bool $remember = false,
    ): void {
        $this->ensureSessionStarted();
        $this->session->set($this->getSessionKey(), $user->getAuthIdentifier());
        $this->session->regenerate();
        $this->cachedUser = $user;

        if ($remember && $this->cookieJar !== null && $this->tokenManager !== null) {
            $this->createRememberToken($user);
        }

        $this->dispatchLoginEvent($user, $remember);
    }

    private function dispatchLoginEvent(
        AuthenticatableInterface $user,
        bool $remember,
    ): void {
        $this->eventDispatcher?->dispatch(new LoginEvent(
            user: $user,
            guard: $this->name,
            remember: $remember,
        ));
    }

    /**
     * @throws RandomException
     */
    private function createRememberToken(
        AuthenticatableInterface $user,
    ): void {
        $token = $this->tokenManager->generate();
        $hashedToken = $this->tokenManager->hash($token);

        $this->provider->updateRememberToken($user, $hashedToken);

        $cookieValue = $user->getAuthIdentifier() . '|' . $token;
        $this->cookieJar->set(
            $this->getRememberCookieName(),
            $cookieValue,
            self::REMEMBER_COOKIE_MINUTES,
        );
    }

    private function getSessionKey(): string
    {
        return 'auth_' . $this->name . '_user_id';
    }

    private function getRememberCookieName(): string
    {
        return 'remember_' . $this->name;
    }

    /**
     * @throws AuthException
     */
    private function ensureSessionStarted(): void
    {
        if (!$this->session->started) {
            throw new AuthException(
                message: 'Session not started',
                context: 'SessionGuard requires an active session',
                suggestion: 'Ensure the session middleware is applied before authentication',
            );
        }
    }

    /**
     * @throws AuthException|RandomException
     */
    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        $user = $this->provider->retrieveById($id);

        if ($user === null) {
            return null;
        }

        $this->login($user);

        return $user;
    }

    /**
     * @throws RandomException
     */
    public function logout(): void
    {
        $user = $this->user();

        if ($user !== null && $this->cookieJar !== null && $this->tokenManager !== null) {
            $this->provider->updateRememberToken($user, null);
            $this->cookieJar->delete($this->getRememberCookieName());
        }

        if ($user !== null) {
            $this->dispatchLogoutEvent($user);
        }

        $this->session->remove($this->getSessionKey());
        $this->cachedUser = null;
    }

    private function dispatchLogoutEvent(
        AuthenticatableInterface $user,
    ): void {
        $this->eventDispatcher?->dispatch(new LogoutEvent(
            user: $user,
            guard: $this->name,
        ));
    }

    public function getName(): string
    {
        return $this->name;
    }
}
