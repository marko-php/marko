<?php

declare(strict_types=1);

namespace Marko\OAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('oauth_clients')]
class OAuthClient extends Entity
{
    #[Column(primaryKey: true, length: 100)]
    public string $id = '';

    #[Column(length: 255)]
    public string $name = '';

    #[Column(length: 255, nullable: true)]
    public ?string $secretHash = null;

    #[Column]
    public bool $confidential = true;

    #[Column(type: 'text')]
    public string $redirectUris = '';

    #[Column(type: 'text', nullable: true)]
    public ?string $allowedScopes = null;

    #[Column(type: 'text')]
    public string $grantTypes = '';

    #[Column]
    public string $createdAt = '';

    #[Column]
    public string $updatedAt = '';

    #[Column(nullable: true)]
    public ?string $revokedAt = null;
}
