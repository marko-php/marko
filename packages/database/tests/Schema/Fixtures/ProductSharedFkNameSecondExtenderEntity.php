<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Names its foreign key the same as ProductSharedFkNameExtenderEntity
#[Table(extends: ProductEntity::class)]
#[ForeignKey(
    columns: ['supplier_id'],
    references: 'suppliers',
    referencedColumns: ['id'],
    name: 'fk_shared',
)]
class ProductSharedFkNameSecondExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?int $supplierId;
}
