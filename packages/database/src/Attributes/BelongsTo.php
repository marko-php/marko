<?php

declare(strict_types=1);

namespace Marko\Database\Attributes;

use Attribute;
use Marko\Database\Entity\Entity;

/**
 * Declares a belongs-to relationship to another entity.
 *
 * foreignKey = property name on THIS entity's class pointing to the related entity
 * (e.g., 'author_id' on Post points to User)
 *
 * @template T of Entity
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class BelongsTo
{
    /**
     * @param class-string<Entity> $entityClass
     */
    public function __construct(
        public string $entityClass,
        public string $foreignKey,
    ) {}
}
