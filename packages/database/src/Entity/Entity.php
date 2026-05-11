<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

abstract class Entity
{
    /** @var array<class-string<EntityExtension>, EntityExtension> */
    private array $extensions = [];

    public function setExtension(EntityExtension $extension): void
    {
        $this->extensions[$extension::class] = $extension;
    }

    /**
     * @template T of EntityExtension
     * @param class-string<T> $class
     * @return T|null
     */
    public function extension(string $class): ?EntityExtension
    {
        return $this->extensions[$class] ?? null;
    }
}
