<?php

declare(strict_types=1);

namespace Marko\Database\Tests\Entity\Fixtures\ExtenderFactory;

use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: ExtenderParentEntity::class)]
class ExtenderWithRelationshipEntity extends Entity
{
    /** @noinspection PhpUnused - Entity property for structural definition */
    #[Column]
    public string $extra;

    /** @noinspection PhpUnused - Entity property for structural definition */
    #[BelongsTo(entityClass: RelatedEntity::class, foreignKey: 'related_id')]
    public ?RelatedEntity $related = null;
}
