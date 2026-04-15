<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;
use Marko\Database\Entity\Entity;

/**
 * Declares a has-many relationship to another entity.
 *
 * foreignKey = property name on the RELATED entity's class pointing back to this entity
 * (e.g., 'post_id' on Comment points back to Post)
 *
 * @template T of Entity
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class HasMany
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public function __construct(
        public string $entityClass,
        public string $foreignKey,
    ) {}
}
