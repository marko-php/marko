<?php

declare(strict_types=1);

namespace Marko\Authentication\Database\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * Owns the schema of the password_reset_tokens table, so db:migrate creates it and db:diff reports drift.
 * DatabasePasswordResetTokenRepository reads and writes the table with its own SQL, not through this entity.
 *
 * One row per user of a provider. Only the SHA-256 hash of the token is stored; created_at is written in the
 * database timezone (`database.timezone`). The expiry is not stored: PasswordResetBroker and
 * auth:clear-reset-tokens derive it from created_at and authentication.password_reset.expire_seconds.
 */
#[Table('password_reset_tokens')]
#[Index('idx_password_reset_tokens_provider_user_id', ['provider', 'user_id'], unique: true)]
#[Index('idx_password_reset_tokens_created_at', ['created_at'])]
class PasswordResetToken extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 100)]
    public string $provider;

    #[Column(length: 255)]
    public string $userId;

    #[Column(length: 64)]
    public string $tokenHash;

    #[Column(type: 'datetime')]
    public DateTimeImmutable $createdAt;
}
