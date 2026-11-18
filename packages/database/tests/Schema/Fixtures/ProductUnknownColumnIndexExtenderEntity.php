<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Index on a column neither the parent nor any extender has
#[Table(extends: ProductEntity::class)]
#[Index(['missing_col'])]
class ProductUnknownColumnIndexExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 50)]
    public string $note;
}
