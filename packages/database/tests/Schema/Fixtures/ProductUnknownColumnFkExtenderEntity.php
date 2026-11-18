<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Foreign key on a column neither the parent nor any extender has
#[Table(extends: ProductEntity::class)]
#[ForeignKey(
    columns: ['missing_id'],
    references: 'regions',
    referencedColumns: ['id'],
)]
class ProductUnknownColumnFkExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 50)]
    public string $note;
}
