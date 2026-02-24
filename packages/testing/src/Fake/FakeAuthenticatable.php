<?php

declare(strict_types=1);

namespace Marko\Testing\Fake;

use Marko\Authentication\AuthenticatableInterface;

class FakeAuthenticatable implements AuthenticatableInterface
{
    public function __construct(
        private int|string $id = 1,
        private string $password = 'hashed-password',
        private ?string $rememberToken = null,
        private string $identifierName = 'id',
        private string $rememberTokenName = 'remember_token',
    ) {}

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return $this->identifierName;
    }

    public function getAuthPassword(): string
    {
        return $this->password;
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(
        ?string $token,
    ): void {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return $this->rememberTokenName;
    }
}
