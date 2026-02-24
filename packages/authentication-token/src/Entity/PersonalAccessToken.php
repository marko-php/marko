<?php

declare(strict_types=1);

namespace Marko\AuthenticationToken\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('personal_access_tokens')]
class PersonalAccessToken extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column('tokenable_type')]
    public string $tokenableType = '';

    #[Column('tokenable_id')]
    public int $tokenableId = 0;

    #[Column]
    public string $name = '';

    #[Column('token_hash', length: 64)]
    public string $tokenHash = '';

    #[Column(type: 'text')]
    public ?string $abilities = null;

    #[Column('last_used_at')]
    public ?string $lastUsedAt = null;

    #[Column('expires_at')]
    public ?string $expiresAt = null;

    #[Column('created_at')]
    public ?string $createdAt = null;
}
