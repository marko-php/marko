<?php

declare(strict_types=1);

namespace Marko\OAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('oauth_access_tokens')]
class OAuthAccessToken extends Entity
{
    #[Column(primaryKey: true, length: 100)]
    public string $id = '';

    #[Column(length: 100)]
    public string $clientId = '';

    #[Column(length: 255, nullable: true)]
    public ?string $userType = null;

    #[Column(length: 100, nullable: true)]
    public ?string $userId = null;

    #[Column(type: 'text', nullable: true)]
    public ?string $scopes = null;

    #[Column]
    public bool $revoked = false;

    #[Column]
    public string $expiresAt = '';

    #[Column]
    public string $createdAt = '';

    #[Column(nullable: true)]
    public ?string $revokedAt = null;
}
