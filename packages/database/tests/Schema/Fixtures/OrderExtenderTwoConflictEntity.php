<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Intentionally adds column 'note' — conflicts with OrderExtenderOneEntity
#[Table(extends: OrderEntity::class)]
class OrderExtenderTwoConflictEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $note;
}
