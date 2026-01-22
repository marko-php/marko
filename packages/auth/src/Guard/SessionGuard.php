<?php

declare(strict_types=1);

namespace Marko\Auth\Guard;

use Marko\Auth\AuthenticatableInterface;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Exceptions\AuthException;
use Marko\Session\Contracts\SessionInterface;

class SessionGuard implements GuardInterface
{
    private const SESSION_KEY = 'auth_user_id';

    private ?AuthenticatableInterface $cachedUser = null;

    public function __construct(
        private SessionInterface $session,
        private UserProviderInterface $provider,
        private string $name = 'session',
    ) {}

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }

        $id = $this->session->get(self::SESSION_KEY);

        if ($id === null) {
            return null;
        }

        $this->cachedUser = $this->provider->retrieveById($id);

        return $this->cachedUser;
    }

    public function id(): int|string|null
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function attempt(
        array $credentials,
    ): bool {
        $user = $this->provider->retrieveByCredentials($credentials);

        if ($user === null) {
            return false;
        }

        if (!$this->provider->validateCredentials($user, $credentials)) {
            return false;
        }

        $this->login($user);

        return true;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        $this->ensureSessionStarted();
        $this->session->set(self::SESSION_KEY, $user->getAuthIdentifier());
        $this->session->regenerate();
        $this->cachedUser = $user;
    }

    private function ensureSessionStarted(): void
    {
        if (!$this->session->isStarted()) {
            throw new AuthException(
                message: 'Session not started',
                context: 'SessionGuard requires an active session',
                suggestion: 'Ensure the session middleware is applied before authentication',
            );
        }
    }

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

    public function logout(): void
    {
        $this->session->remove(self::SESSION_KEY);
        $this->cachedUser = null;
    }

    public function setProvider(
        UserProviderInterface $provider,
    ): void {
        $this->provider = $provider;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
