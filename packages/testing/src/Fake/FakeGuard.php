<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Contracts\UserProviderInterface;
use Marko\Testing\Exceptions\AssertionFailedException;

class FakeGuard implements GuardInterface
{
    /** @var array<array<string, mixed>> */
    public private(set) array $attempts = [];

    /** @var list<bool> The remember flag of each attempt, in the order of $attempts */
    public private(set) array $attemptRemembers = [];

    public private(set) bool $logoutCalled = false;

    public ?UserProviderInterface $provider = null {
        set {
            $this->provider = $value;
        }
    }

    private ?AuthenticatableInterface $currentUser = null;

    public function __construct(
        private readonly string $name = 'test',
        private bool $attemptResult = true,
    ) {}

    public function check(): bool
    {
        return $this->currentUser !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?AuthenticatableInterface
    {
        return $this->currentUser;
    }

    public function id(): int|string|null
    {
        return $this->currentUser?->getAuthIdentifier();
    }

    /**
     * @param array<string, mixed> $credentials
     */
    public function attempt(
        array $credentials,
        bool $remember = false,
    ): bool {
        $this->attempts[] = $credentials;
        $this->attemptRemembers[] = $remember;

        return $this->attemptResult;
    }

    public function login(
        AuthenticatableInterface $user,
    ): void {
        $this->currentUser = $user;
    }

    public function loginById(
        int|string $id,
    ): ?AuthenticatableInterface {
        return null;
    }

    public function logout(): void
    {
        $this->logoutCalled = true;
        $this->currentUser = null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setUser(?AuthenticatableInterface $user): void
    {
        $this->currentUser = $user;
    }

    public function setAttemptResult(bool $result): void
    {
        $this->attemptResult = $result;
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertAuthenticated(): void
    {
        if (!$this->check()) {
            throw AssertionFailedException::expectedAuthenticated();
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertGuest(): void
    {
        if (!$this->guest()) {
            throw AssertionFailedException::unexpectedGuest();
        }
    }

    /**
     * @param (callable(array<string, mixed> $credentials, bool $remember): bool)|null $callback
     *
     * @throws AssertionFailedException
     */
    public function assertAttempted(?callable $callback = null): void
    {
        if ($this->attempts === []) {
            throw AssertionFailedException::unexpectedEmpty('authentication attempts');
        }

        if ($callback !== null) {
            $found = array_any(
                array_keys($this->attempts),
                fn (int $index) => $callback($this->attempts[$index], $this->attemptRemembers[$index]),
            );

            if (!$found) {
                throw AssertionFailedException::unexpectedEmpty('authentication attempts matching callback');
            }
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNotAttempted(): void
    {
        if ($this->attempts !== []) {
            throw AssertionFailedException::expectedEmpty('authentication attempts');
        }
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertLoggedOut(): void
    {
        if (!$this->logoutCalled) {
            throw AssertionFailedException::unexpectedEmpty('logout calls');
        }
    }

    public function clear(): void
    {
        $this->attempts = [];
        $this->attemptRemembers = [];
        $this->logoutCalled = false;
        $this->currentUser = null;
    }
}
