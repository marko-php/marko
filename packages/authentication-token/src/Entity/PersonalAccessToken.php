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

    #[Column]
    public string $tokenableType = '';

    #[Column]
    public int $tokenableId = 0;

    #[Column]
    public string $name = '';

    #[Column(length: 64)]
    public string $tokenHash = '';

    #[Column(type: 'text')]
    public ?string $abilities = null;

    /** @noinspection PhpUnused */
    #[Column]
    public ?string $lastUsedAt = null;

    #[Column]
    public ?string $expiresAt = null;

    #[Column]
    public ?string $createdAt = null;
}
