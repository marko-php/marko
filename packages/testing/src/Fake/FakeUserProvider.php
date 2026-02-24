<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

class FakeUserProvider implements UserProviderInterface
{
    /** @var array{user: AuthenticatableInterface, token: ?string}|null */
    public private(set) ?array $lastRememberTokenUpdate = null;

    /**
     * @param array<int|string, AuthenticatableInterface> $users keyed by identifier
     * @param ?callable $credentialValidator receives (AuthenticatableInterface, array $credentials), returns bool
     */
    public function __construct(
        private array $users = [],
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
        if (isset($credentials['identifier']) && isset($this->users[$credentials['identifier']])) {
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
    ): void {
        $this->lastRememberTokenUpdate = [
            'user' => $user,
            'token' => $token,
        ];
        $user->setRememberToken($token);
    }
}
