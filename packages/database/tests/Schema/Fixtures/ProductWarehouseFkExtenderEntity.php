<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Adds a column and a foreign key on it
#[Table(extends: ProductEntity::class)]
#[ForeignKey(
    columns: ['warehouse_id'],
    references: 'warehouses',
    referencedColumns: ['id'],
    onDelete: 'CASCADE',
)]
class ProductWarehouseFkExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?int $warehouseId;
}
