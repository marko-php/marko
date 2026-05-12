<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

abstract class Entity
{
    /**
     * Return all companions attached to this entity, keyed by class name.
     *
     * @return array<class-string, Entity>
     */
    public function companions(): array
    {
        return EntityCompanionStorage::instance()->get($this);
    }

    /**
     * Look up a companion by class.
     *
     * @template T of Entity
     * @param class-string<T> $class
     * @return T|null
     */
    public function companion(string $class): ?Entity
    {
        return EntityCompanionStorage::instance()->get($this)[$class] ?? null;
    }

    /**
     * Attach a companion to this entity (public API for user code).
     *
     * Delegates into the shared EntityCompanionStorage so that companions
     * attached here are visible to any code that reads via the same storage
     * (including EntityHydrator's internal attach).
     */
    public function attachCompanion(Entity $companion): void
    {
        EntityCompanionStorage::instance()->attach($this, $companion);
    }
}
