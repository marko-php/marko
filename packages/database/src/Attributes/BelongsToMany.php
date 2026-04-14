<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;
use Marko\Database\Entity\Entity;

/**
 * Declares a belongs-to-many relationship through a pivot entity.
 *
 * foreignKey = property name on the PIVOT entity pointing to this entity
 * relatedKey = property name on the PIVOT entity pointing to the related entity
 *
 * @template T of Entity
 * @template P of Entity
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class BelongsToMany
{
    /**
     * @param class-string<Entity> $entityClass
     * @param class-string<Entity> $pivotClass
     */
    public function __construct(
        public string $entityClass,
        public string $pivotClass,
        public string $foreignKey,
        public string $relatedKey,
    ) {}
}
