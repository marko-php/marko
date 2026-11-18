<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Schema\Fixtures;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ForeignKey;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

// Gives team_id a second foreign key, which ProductFkParentEntity already gives it through references:
#[Table(extends: ProductFkParentEntity::class)]
#[ForeignKey(
    columns: ['team_id'],
    references: 'teams',
    referencedColumns: ['id'],
    name: 'fk_products_team',
)]
class ProductFkParentTeamExtenderEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column(length: 50)]
    public string $note;
}
