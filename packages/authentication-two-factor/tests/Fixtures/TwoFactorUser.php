<?php

declare(strict_types=1);

namespace Marko\AuthenticationTwoFactor\Tests\Fixtures;

use DateTimeImmutable;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorAuthenticatableInterface;

/**
 * A user entity that stores its two-factor state in memory.
 */
class TwoFactorUser implements TwoFactorAuthenticatableInterface
{
    private ?string $twoFactorSecret = null;

    /** @var list<string> */
    private array $twoFactorRecoveryCodes = [];

    private ?DateTimeImmutable $twoFactorConfirmedAt = null;

    private ?int $twoFactorLastUsedStep = null;

    private ?string $rememberToken = null;

    private ?DateTimeImmutable $rememberTokenExpiresAt = null;

    public function __construct(
        private readonly int|string $id = 7,
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
        return 'hash';
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

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(
        ?string $secret,
    ): void {
        $this->twoFactorSecret = $secret;
    }

    public function getTwoFactorRecoveryCodes(): array
    {
        return $this->twoFactorRecoveryCodes;
    }

    public function setTwoFactorRecoveryCodes(
        array $hashes,
    ): void {
        $this->twoFactorRecoveryCodes = $hashes;
    }

    public function getTwoFactorConfirmedAt(): ?DateTimeImmutable
    {
        return $this->twoFactorConfirmedAt;
    }

    public function setTwoFactorConfirmedAt(
        ?DateTimeImmutable $at,
    ): void {
        $this->twoFactorConfirmedAt = $at;
    }

    public function getTwoFactorLastUsedStep(): ?int
    {
        return $this->twoFactorLastUsedStep;
    }

    public function setTwoFactorLastUsedStep(
        ?int $step,
    ): void {
        $this->twoFactorLastUsedStep = $step;
    }
}
