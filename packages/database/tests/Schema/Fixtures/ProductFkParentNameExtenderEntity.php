<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Names its foreign key the same as the one ProductFkParentEntity declares
#[Table(extends: ProductFkParentEntity::class)]
#[ForeignKey(
    columns: ['region_id'],
    references: 'regions',
    referencedColumns: ['id'],
    name: 'fk_shared',
)]
class ProductFkParentNameExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?int $regionId;
}
