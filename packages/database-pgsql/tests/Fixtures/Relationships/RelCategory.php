<?php

declare(strict_types=1);

namespace Marko\Database\PgSql\Tests\Fixtures\Relationships;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

/**
 * A self-referential category tree: parent and children live in the same table.
 */
#[Table('rel_categories')]
class RelCategory extends Entity
{
    #[Column(primaryKey: true)]
    public int $id;

    #[Column]
    public ?int $parentId = null;

    #[Column]
    public string $name;

    #[BelongsTo(RelCategory::class, foreignKey: 'parentId')]
    public ?RelCategory $parent = null;

    /** @var array<RelCategory> */
    #[HasMany(RelCategory::class, foreignKey: 'parentId')]
    public array $children = [];
}
