<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Holds parsed metadata from an extension class attributes.
 *
 * @template T of EntityExtension
 */
readonly class ExtensionMetadata
{
    /**
     * @param class-string<T> $extensionClass
     * @param class-string<Entity> $entityClass
     * @param array<string, PropertyMetadata> $properties Property name => metadata
     * @param array<ColumnMetadata> $columns
     */
    public function __construct(
        public string $extensionClass,
        public string $entityClass,
        public array $properties = [],
        public array $columns = [],
    ) {}

    /**
     * Get property metadata by property name.
     */
    public function getProperty(string $name): ?PropertyMetadata
    {
        return $this->properties[$name] ?? null;
    }
}
