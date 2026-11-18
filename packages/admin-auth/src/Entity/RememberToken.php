<?php

declare(strict_types=1);

namespace Marko\AdminAuth\Entity;

use DateTimeImmutable;
use Marko\Authentication\Token\RememberTokenRecord;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * One device's remember-me token. db:migrate creates the remember_tokens table from this entity;
 * RememberTokenRepository reads and writes it for every session guard (the guard column keeps them apart).
 *
 * Only the SHA-256 hash of the validator is stored; the selector is the lookup key.
 */
#[Table('remember_tokens')]
#[Index(['expires_at'])]
class RememberToken extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 100)]
    public string $guard;

    #[Column(length: 100)]
    public string $userId;

    #[Column(length: 64, unique: true)]
    public string $selector;

    #[Column(length: 64)]
    public string $validatorHash;

    #[Column(type: 'datetime')]
    public DateTimeImmutable $expiresAt;

    #[Column]
    public ?string $userAgent = null;

    #[Column(type: 'datetime')]
    public ?DateTimeImmutable $createdAt = null;

    public function toRecord(): RememberTokenRecord
    {
        return new RememberTokenRecord(
            guard: $this->guard,
            userId: $this->userId,
            selector: $this->selector,
            validatorHash: $this->validatorHash,
            expiresAt: $this->expiresAt,
            userAgent: $this->userAgent,
        );
    }
}
