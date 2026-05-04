<?php

declare(strict_types=1);

namespace Marko\OAuth\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('oauth_scopes')]
class OAuthScope extends Entity
{
    #[Column(primaryKey: true, length: 100)]
    public string $id = '';

    #[Column(length: 255)]
    public string $label = '';

    #[Column]
    public bool $default = false;

    #[Column]
    public string $createdAt = '';

    #[Column]
    public string $updatedAt = '';
}
