<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Fixtures\ExtenderFactory;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: ExtenderParentEntity::class)]
#[Index('idx_extra', ['extra'])]
class ExtenderWithIndexEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;
}
