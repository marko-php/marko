<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use DateTimeImmutable;
use Marko\Authentication\CanResetPasswordInterface;

/**
 * A user entity that supports password reset: its password hash can be replaced.
 */
class ResettableUser implements CanResetPasswordInterface
{
    private ?string $rememberToken = null;

    private ?DateTimeImmutable $rememberTokenExpiresAt = null;

    public function __construct(
        private readonly int|string $id,
        private readonly string $email,
        private string $password = 'old-hash',
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

    public function setAuthPassword(
        string $hash,
    ): void {
        $this->password = $hash;
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->email;
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

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->rememberTokenExpiresAt;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {
        $this->rememberTokenExpiresAt = $expiresAt;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
