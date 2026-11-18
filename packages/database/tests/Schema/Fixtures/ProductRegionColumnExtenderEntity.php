<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Adds the column ProductRegionFkExtenderEntity points its foreign key at
#[Table(extends: ProductEntity::class)]
class ProductRegionColumnExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?int $regionId;
}
