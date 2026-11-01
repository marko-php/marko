<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\Relationships;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('rel_tags')]
class RelTag extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public string $label;
}
