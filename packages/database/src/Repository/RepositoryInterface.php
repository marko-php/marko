<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Exceptions\BatchInsertException;
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
    public function find(int|string $id): ?Entity;

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @return TEntity The entity
     * @throws RepositoryException When entity is not found
     */
    public function findOrFail(int|string $id): Entity;

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

    /**
     * Insert multiple entities in a single multi-row INSERT statement.
     *
     * This is an explicit escape hatch for import and seed operations that need
     * to persist large numbers of entities efficiently.
     *
     * **Caveats:**
     * - Relationships are NOT auto-persisted. You must persist related entities
     *   separately before or after calling this method.
     * - `EntityCreating` and `EntityCreated` lifecycle events fire synchronously
     *   for each entity. For high-throughput imports where observer work is
     *   expensive, consider marking observers async (see `marko/queue`) or
     *   dropping to the raw query builder for pure-SQL bulk inserts that bypass
     *   the entity layer entirely.
     * - The column set is derived from the first entity and enforced across all
     *   entities in the batch; mixing entities whose column sets differ will
     *   throw a `BatchInsertException`.
     * - The method opens its own transaction if none is active, ensuring all
     *   rows are rolled back on failure. If an outer transaction is already
     *   active the method participates in it — the caller is responsible for
     *   committing or rolling back.
     * - MySQL id recovery uses `LAST_INSERT_ID()` + sequential row-count math
     *   and is only reliable when `innodb_autoinc_lock_mode` is 0 or 1
     *   (traditional/consecutive). PostgreSQL uses `INSERT … RETURNING` for
     *   exact id recovery.
     *
     * @param array<Entity> $entities Entities to insert — must all be the same class
     * @throws BatchInsertException|RepositoryException
     *                              or column sets differ across the batch
     */
    public function insertBatch(array $entities): void;
}
