<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

class FakeUserProvider implements UserProviderInterface
{
    /** @var array{user: AuthenticatableInterface, token: ?string, expiresAt: ?DateTimeImmutable}|null */
    public private(set) ?array $lastRememberTokenUpdate = null;

    /** @var list<array{user: AuthenticatableInterface, credentials: array<string, mixed>}> */
    public private(set) array $rehashChecks = [];

    /**
     * @param array<int|string, AuthenticatableInterface> $users keyed by identifier
     */
    public function __construct(
        private readonly array $users = [],
        private $credentialValidator = null,
    ) {}

    public function retrieveById(
        int|string $identifier,
    ): ?AuthenticatableInterface {
        return $this->users[$identifier] ?? null;
    }

    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface {
        if (
            isset($credentials['identifier'])
            && isset($this->users[$credentials['identifier']])
        ) {
            return $this->users[$credentials['identifier']];
        }

        return $this->users ? array_values($this->users)[0] : null;
    }

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool {
        if ($this->credentialValidator !== null) {
            return ($this->credentialValidator)($user, $credentials);
        }

        return true;
    }

    /**
     * The fake stores no password hashes, so it only records the call.
     */
    public function rehashPasswordIfNeeded(
        AuthenticatableInterface $user,
        array $credentials,
    ): void {
        $this->rehashChecks[] = [
            'user' => $user,
            'credentials' => $credentials,
        ];
    }

    public function retrieveByRememberToken(
        int|string $identifier,
        string $token,
    ): ?AuthenticatableInterface {
        $user = $this->users[$identifier] ?? null;

        if ($user === null) {
            return null;
        }

        return $user->getRememberToken() === $token ? $user : null;
    }

    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
        ?DateTimeImmutable $expiresAt,
    ): void {
        $this->lastRememberTokenUpdate = [
            'user' => $user,
            'token' => $token,
            'expiresAt' => $expiresAt,
        ];
        $user->setRememberToken($token);
        $user->setRememberTokenExpiresAt($expiresAt);
    }
}
