<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Declares a foreign key name of 65 bytes, over the 63-byte identifier limit
#[Table(extends: ProductEntity::class)]
#[ForeignKey(
    columns: ['region_id'],
    references: 'regions',
    referencedColumns: ['id'],
    name: 'fk_products_warehouse_location_code_and_supplier_reference_lookup',
)]
class ProductLongFkExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public ?int $regionId;
}
