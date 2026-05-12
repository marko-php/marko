<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use WeakMap;

/**
 * Shared companion-bag storage for Entity instances.
 *
 * A single instance of this class is shared between Entity (public attach API)
 * and EntityHydrator (internal attach during hydration), ensuring both surfaces
 * read and write the same WeakMap — no duplicate maps.
 *
 * The static instance() accessor is the integration point: Entity delegates
 * its public API here, and EntityHydrator can be given the same instance via
 * constructor injection or can call instance() directly.
 */
class EntityCompanionStorage
{
    private static EntityCompanionStorage $instance;

    /**
     * @var WeakMap<Entity, array<class-string, Entity>>
     */
    private WeakMap $companions;

    public function __construct()
    {
        $this->companions = new WeakMap();
    }

    /**
     * Return the shared singleton instance.
     *
     * EntityHydrator and Entity both route through this instance so there
     * is exactly one WeakMap in the process.
     */
    public static function instance(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Replace the shared instance (used in tests to get a clean slate).
     */
    public static function swap(EntityCompanionStorage $storage): void
    {
        self::$instance = $storage;
    }

    /**
     * Get all companions for an entity.
     *
     * @return array<class-string, Entity>
     */
    public function get(Entity $entity): array
    {
        return $this->companions[$entity] ?? [];
    }

    /**
     * Attach a companion to an entity, keyed by the companion's class.
     */
    public function attach(Entity $entity, Entity $companion): void
    {
        $bag = $this->companions[$entity] ?? [];
        $bag[$companion::class] = $companion;
        $this->companions[$entity] = $bag;
    }
}
