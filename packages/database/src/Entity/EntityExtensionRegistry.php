<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

/**
 * Maps entity class strings to their registered extension class strings.
 */
class EntityExtensionRegistry
{
    /** @var array<class-string<Entity>, list<class-string<EntityExtension>>> */
    private array $extensions = [];

    /**
     * @param class-string<Entity> $entityClass
     * @param class-string<EntityExtension> $extensionClass
     */
    public function register(string $entityClass, string $extensionClass): void
    {
        if (!isset($this->extensions[$entityClass])) {
            $this->extensions[$entityClass] = [];
        }

        if (!in_array($extensionClass, $this->extensions[$entityClass], true)) {
            $this->extensions[$entityClass][] = $extensionClass;
        }
    }

    /**
     * @param class-string<Entity> $entityClass
     * @return array<class-string<EntityExtension>>
     */
    public function getExtensions(string $entityClass): array
    {
        return $this->extensions[$entityClass] ?? [];
    }
}
