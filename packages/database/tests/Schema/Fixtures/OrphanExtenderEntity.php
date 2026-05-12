<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Extends ProductEntity but used in tests where ProductEntity is NOT in the input
#[Table(extends: ProductEntity::class)]
class OrphanExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}
