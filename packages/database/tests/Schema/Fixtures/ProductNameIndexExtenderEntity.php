<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Derives the same index name as the parent's #[Index(['name'])]
#[Table(extends: ProductNameIndexedEntity::class)]
#[Index(['name'])]
class ProductNameIndexExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 100)]
    public string $tag;
}
