<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\Relationships;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('rel_studio_profiles')]
class RelStudioProfile extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public int $studioId;

    #[Column]
    public bool $verified;
}
