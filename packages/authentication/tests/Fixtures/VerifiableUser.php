<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use DateTimeImmutable;
use Marko\Authentication\MustVerifyEmailInterface;

/**
 * A user entity whose email address must be verified, backed by a nullable verification time.
 */
class VerifiableUser implements MustVerifyEmailInterface
{
    public function __construct(
        private readonly int|string $id,
        public string $email,
        public ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    public function hasVerifiedEmail(): bool
    {
        return $this->emailVerifiedAt !== null;
    }

    public function markEmailAsVerified(
        DateTimeImmutable $verifiedAt,
    ): void {
        $this->emailVerifiedAt = $verifiedAt;
    }

    public function getEmailForVerification(): string
    {
        return $this->email;
    }

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
        return 'hash';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken(
        ?string $token,
    ): void {}

    public function getRememberTokenExpiresAt(): ?DateTimeImmutable
    {
        return null;
    }

    public function setRememberTokenExpiresAt(
        ?DateTimeImmutable $expiresAt,
    ): void {}

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }
}
