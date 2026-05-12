<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Intentionally adds column 'status' — conflicts with the parent's own column
#[Table(extends: OrderEntity::class)]
class OrderExtenderConflictsWithParentEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $status;
}
