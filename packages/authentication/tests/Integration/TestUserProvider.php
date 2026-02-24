<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Integration;

use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

/**
 * Test user provider that tracks remember token updates.
 */
class TestUserProvider implements UserProviderInterface
{
    public ?string $lastUpdatedRememberToken = null;

    public function __construct(
        private readonly ?TestUser $userById = null,
        private readonly ?TestUser $userByCredentials = null,
        private readonly bool $credentialsValid = false,
        private readonly ?TestUser $userByRememberToken = null,
    ) {}

    public function retrieveById(
        int|string $identifier,
    ): ?AuthenticatableInterface {
        return $this->userById;
    }

    public function retrieveByCredentials(
        array $credentials,
    ): ?AuthenticatableInterface {
        return $this->userByCredentials;
    }

    public function validateCredentials(
        AuthenticatableInterface $user,
        array $credentials,
    ): bool {
        return $this->credentialsValid;
    }

    public function retrieveByRememberToken(
        int|string $identifier,
        string $token,
    ): ?AuthenticatableInterface {
        return $this->userByRememberToken;
    }

    public function updateRememberToken(
        AuthenticatableInterface $user,
        ?string $token,
    ): void {
        $this->lastUpdatedRememberToken = $token;
        $user->setRememberToken($token);
    }
}
