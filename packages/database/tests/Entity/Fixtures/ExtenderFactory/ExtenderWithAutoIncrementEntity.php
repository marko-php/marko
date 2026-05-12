<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Fixtures\ExtenderFactory;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: ExtenderParentEntity::class)]
class ExtenderWithAutoIncrementEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;
}
