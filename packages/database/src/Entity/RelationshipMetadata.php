<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds metadata about a single entity relationship.
 */
readonly class RelationshipMetadata
{
    public function __construct(
        public string $propertyName,
        public RelationshipType $type,
        public string $relatedClass,
        public string $foreignKey,
        public ?string $relatedKey = null,
        public ?string $pivotClass = null,
    ) {}

    public function isSingular(): bool
    {
        return $this->type === RelationshipType::HasOne
            || $this->type === RelationshipType::BelongsTo;
    }

    public function isCollection(): bool
    {
        return $this->type === RelationshipType::HasMany
            || $this->type === RelationshipType::BelongsToMany;
    }
}
