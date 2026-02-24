<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Integration;

use Marko\Authentication\AuthenticatableInterface;

/**
 * Test user for integration testing.
 */
class TestUser implements AuthenticatableInterface
{
    private ?string $token = null;

    public function __construct(
        private readonly int|string $id = 1,
        private readonly string $password = 'hashed',
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->token;
    }

    public function setRememberToken(
        ?string $token,
    ): void {
        $this->token = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
