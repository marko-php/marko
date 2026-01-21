<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;

/**
 * Interface for entity repositories providing data access methods.
 */
interface RepositoryInterface
{
    /**
     * Find an entity by its primary key.
     *
     * @param int $id The entity ID
     * @return Entity|null The entity or null if not found
     */
    public function find(int $id): ?Entity;

    /**
     * Find all entities in the repository.
     *
     * @return array<Entity> All entities
     */
    public function findAll(): array;

    /**
     * Find entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return array<Entity> Matching entities
     */
    public function findBy(array $criteria): array;

    /**
     * Find a single entity matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return Entity|null The entity or null if not found
     */
    public function findOneBy(array $criteria): ?Entity;

    /**
     * Save an entity (insert or update).
     *
     * @param Entity $entity The entity to save
     */
    public function save(Entity $entity): void;

    /**
     * Delete an entity.
     *
     * @param Entity $entity The entity to delete
     */
    public function delete(Entity $entity): void;
}
