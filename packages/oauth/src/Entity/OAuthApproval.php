<?php

declare(strict_types=1);

namespace Marko\OAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('oauth_approvals')]
class OAuthApproval extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(length: 255)]
    public string $userType = '';

    #[Column(length: 100)]
    public string $userId = '';

    #[Column(length: 100)]
    public string $clientId = '';

    #[Column(type: 'text')]
    public string $scopes = '';

    #[Column]
    public string $expiresAt = '';

    #[Column]
    public string $createdAt = '';

    #[Column(nullable: true)]
    public ?string $revokedAt = null;
}
