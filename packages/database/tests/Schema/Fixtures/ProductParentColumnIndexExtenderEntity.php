<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Index on a parent column and an extender column
#[Table(extends: ProductEntity::class)]
#[Index(['name', 'note'])]
class ProductParentColumnIndexExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 50)]
    public string $note;
}
