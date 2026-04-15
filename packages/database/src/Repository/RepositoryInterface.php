<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\RepositoryException;

/**
 * Interface for entity repositories providing data access methods.
 *
 * @template TEntity of Entity
 */
interface RepositoryInterface
{
    /**
     * Find an entity by its primary key.
     *
     * @return TEntity|null The entity or null if not found
     */
    public function find(int $id): ?Entity;

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @return TEntity The entity
     * @throws RepositoryException When entity is not found
     */
    public function findOrFail(int $id): Entity;

    /**
     * Find all entities in the repository.
     *
     * @return EntityCollection<TEntity> All entities
     */
    public function findAll(): EntityCollection;

    /**
     * Find entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return EntityCollection<TEntity> Matching entities
     */
    public function findBy(array $criteria): EntityCollection;

    /**
     * Find a single entity matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return TEntity|null The entity or null if not found
     */
    public function findOneBy(array $criteria): ?Entity;

    /**
     * Check if any entity matches the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     */
    public function existsBy(array $criteria): bool;

    /**
     * Save an entity (insert or update).
     *
     * @param Entity $entity The entity to save
     * @throws RepositoryException
     */
    public function save(Entity $entity): void;

    /**
     * Delete an entity.
     *
     * @param Entity $entity The entity to delete
     * @throws RepositoryException
     */
    public function delete(Entity $entity): void;
}
