<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;
use Marko\Database\Entity\Entity;

/**
 * Declares a has-one relationship to another entity.
 *
 * foreignKey = property name on the RELATED entity's class pointing back to this entity
 * (e.g., 'user_id' on Profile points back to User)
 *
 * @template T of Entity
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class HasOne
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public function __construct(
        public string $entityClass,
        public string $foreignKey,
    ) {}
}
