<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\Relationships;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('rel_projects')]
class RelProject extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public int $studioId;

    #[Column]
    public string $status;

    #[BelongsTo(RelStudio::class, foreignKey: 'studioId')]
    public ?RelStudio $studio = null;
}
