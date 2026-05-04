<?php

declare(strict_types=1);

namespace Marko\OAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('oauth_auth_codes')]
class OAuthAuthCode extends Entity
{
    #[Column(primaryKey: true, length: 100)]
    public string $id = '';

    #[Column(length: 100)]
    public string $clientId = '';

    #[Column(length: 255)]
    public string $userType = '';

    #[Column(length: 100)]
    public string $userId = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $scopes = null;

    #[Column(type: 'text')]
    public string $redirectUri = '';

    #[Column(length: 128, nullable: true)]
    public ?string $codeChallenge = null;

    #[Column(length: 20, nullable: true)]
    public ?string $codeChallengeMethod = null;

    #[Column]
    public bool $revoked = false;

    #[Column]
    public string $expiresAt = '';

    #[Column]
    public string $createdAt = '';
}
