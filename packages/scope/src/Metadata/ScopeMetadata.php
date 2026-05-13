<?php

declare(strict_types=1);

namespace Marko\Scope\Metadata;

/**
 * Holds parsed scope metadata for an entity class.
 */
readonly class ScopeMetadata
{
    /**
     * @param array<string, list<string>> $scopedProperties Map of property name => list of axes
     */
    public function __construct(
        private array $scopedProperties = [],
    ) {}

    public function hasScopedProperties(): bool
    {
        return $this->scopedProperties !== [];
    }

    public function isScoped(string $property): bool
    {
        return isset($this->scopedProperties[$property]);
    }

    /**
     * @return list<string>
     */
    public function scopedProperties(): array
    {
        return array_keys($this->scopedProperties);
    }

    /**
     * @return list<string>
     */
    public function axesForProperty(string $property): array
    {
        return $this->scopedProperties[$property] ?? [];
    }
}
