<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * One registered passkey. db:migrate creates the passkey_credentials table
 * from this entity.
 *
 * The owner is named explicitly by user provider and user id, the same pair
 * a guard resolves users with, so two providers with overlapping ids never
 * share passkeys.
 */
#[Table('passkey_credentials')]
#[Index('idx_passkey_credentials_owner', ['provider', 'user_id'])]
class PasskeyCredential extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    /** The credential id, base64url encoded. */
    #[Column(length: 512, unique: true)]
    public string $credentialId;

    /** The authentication.providers name the owner comes from. */
    #[Column(length: 100)]
    public string $provider;

    #[Column(length: 100)]
    public string $userId;

    /** A label the user chose, e.g. "MacBook Touch ID". */
    #[Column(length: 255)]
    public string $name;

    /** The credential public key, PEM encoded. */
    #[Column(type: 'text')]
    public string $publicKey;

    #[Column]
    public int $signCount = 0;

    /** @var list<string> Transport hints the authenticator reported, e.g. ["internal", "hybrid"] */
    #[Column]
    public array $transports = [];

    #[Column(type: 'datetime')]
    public DateTimeImmutable $createdAt;

    #[Column(type: 'datetime')]
    public ?DateTimeImmutable $lastUsedAt = null;
}
