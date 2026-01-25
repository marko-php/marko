<?php

declare(strict_types=1);

namespace Marko\Auth;

use Marko\Auth\Config\AuthConfig;
use Marko\Auth\Contracts\GuardInterface;
use Marko\Auth\Contracts\UserProviderInterface;
use Marko\Auth\Exceptions\AuthException;
use Marko\Auth\Guard\SessionGuard;
use Marko\Auth\Guard\TokenGuard;
use Marko\Session\Contracts\SessionInterface;

class AuthManager
{
    /** @var array<string, GuardInterface> */
    private array $guards = [];

    public function __construct(
        private AuthConfig $config,
        private SessionInterface $session,
        private UserProviderInterface $provider,
    ) {}

    public function guard(
        ?string $name = null,
    ): GuardInterface {
        $name ??= $this->config->defaultGuard();

        if (isset($this->guards[$name])) {
            return $this->guards[$name];
        }

        $guardsConfig = $this->config->guards();
        $guardConfig = $guardsConfig[$name] ?? [];
        $driver = $guardConfig['driver'] ?? 'session';

        $guard = $this->createGuard($driver, $name);

        $this->guards[$name] = $guard;

        return $guard;
    }

    private function createGuard(
        string $driver,
        string $name,
    ): GuardInterface {
        return match ($driver) {
            'session' => $this->createSessionGuard($name),
            'token' => $this->createTokenGuard($name),
            default => throw new AuthException(
                message: "Unknown guard driver: $driver",
                context: "Guard '$name' configured with driver '$driver'",
                suggestion: "Use 'session' or 'token' as the guard driver, or register a custom driver",
            ),
        };
    }

    private function createSessionGuard(
        string $name,
    ): SessionGuard {
        $guard = new SessionGuard(
            session: $this->session,
            provider: $this->provider,
            name: $name,
        );

        return $guard;
    }

    private function createTokenGuard(
        string $name,
    ): TokenGuard {
        $guard = new TokenGuard(
            name: $name,
            provider: $this->provider,
        );

        return $guard;
    }

    public function check(): bool
    {
        return $this->guard()->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->guard()->user();
    }

    public function id(): int|string|null
    {
        return $this->guard()->id();
    }

    /**
     * Attempt to authenticate a user using the given credentials.
     *
     * @param array<string, mixed> $credentials
     */
    public function attempt(
        array $credentials,
    ): bool {
        return $this->guard()->attempt($credentials);
    }

    public function logout(): void
    {
        $this->guard()->logout();
    }
}
