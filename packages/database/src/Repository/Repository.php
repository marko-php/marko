<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use BackedEnum;
use DateTimeImmutable;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use ReflectionClass;

/**
 * Abstract base class for entity repositories.
 *
 * Concrete repositories must define the ENTITY_CLASS constant
 * specifying which entity class they manage.
 *
 * @template TEntity of Entity
 * @implements RepositoryInterface<TEntity>
 */
abstract class Repository implements RepositoryInterface
{
    /**
     * The entity class this repository manages.
     * Must be defined in concrete repository classes.
     */
    protected const string ENTITY_CLASS = '';

    protected readonly EntityMetadata $metadata;

    /**
     * @param ConnectionInterface $connection Database connection
     * @param EntityMetadataFactory $metadataFactory Factory for entity metadata
     * @param EntityHydrator $hydrator Entity hydrator
     * @param QueryBuilderFactoryInterface|null $queryBuilderFactory Optional factory that creates QueryBuilderInterface instances
     * @param EventDispatcherInterface|null $eventDispatcher Optional event dispatcher for lifecycle events
     *
     * @throws RepositoryException
     */
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly EntityMetadataFactory $metadataFactory,
        protected readonly EntityHydrator $hydrator,
        protected readonly ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        protected readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->validateEntityClass();
        $this->metadata = $this->metadataFactory->parse(static::ENTITY_CLASS);
    }

    /**
     * Find an entity by its primary key.
     *
     * @return TEntity|null
     */
    public function find(
        int $id,
    ): ?Entity {
        $primaryKey = $this->metadata->getPrimaryKeyProperty();
        $columnName = $primaryKey?->columnName ?? 'id';

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?',
            $this->metadata->tableName,
            $columnName,
        );

        $rows = $this->connection->query($sql, [$id]);

        if (count($rows) === 0) {
            return null;
        }

        return $this->hydrator->hydrate(
            static::ENTITY_CLASS,
            $rows[0],
            $this->metadata,
        );
    }

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @return TEntity
     * @throws RepositoryException When entity is not found
     */
    public function findOrFail(
        int $id,
    ): Entity {
        $entity = $this->find($id);

        if ($entity === null) {
            throw RepositoryException::entityNotFound(static::ENTITY_CLASS, $id);
        }

        return $entity;
    }

    /**
     * Find all entities in the repository.
     *
     * @return array<TEntity>
     */
    public function findAll(): array
    {
        $sql = sprintf('SELECT * FROM %s', $this->metadata->tableName);
        $rows = $this->connection->query($sql);

        return array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Find entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return array<TEntity>
     */
    public function findBy(
        array $criteria,
    ): array {
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();
        $conditions = [];
        $bindings = [];

        foreach ($criteria as $property => $value) {
            $column = $propertyToColumn[$property] ?? $property;
            $conditions[] = "$column = ?";
            $bindings[] = $value;
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->metadata->tableName,
            implode(' AND ', $conditions),
        );

        $rows = $this->connection->query($sql, $bindings);

        return array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );
    }

    /**
     * Find a single entity matching the given criteria.
     *
     * @return TEntity|null
     */
    public function findOneBy(
        array $criteria,
    ): ?Entity {
        $results = $this->findBy($criteria);

        return $results[0] ?? null;
    }

    /**
     * Save an entity (insert or update).
     *
     * @throws RepositoryException
     */
    public function save(
        Entity $entity,
    ): void {
        $this->validateEntityType($entity);

        if ($this->hydrator->isNew($entity, $this->metadata)) {
            $this->eventDispatcher?->dispatch(new EntityCreating($entity, static::ENTITY_CLASS));
            $this->insert($entity);
            $this->eventDispatcher?->dispatch(new EntityCreated($entity, static::ENTITY_CLASS));
        } else {
            $this->eventDispatcher?->dispatch(new EntityUpdating($entity, static::ENTITY_CLASS));
            $this->update($entity);
            $this->eventDispatcher?->dispatch(new EntityUpdated($entity, static::ENTITY_CLASS));
        }
    }

    /**
     * Delete an entity.
     *
     * @throws RepositoryException
     */
    public function delete(
        Entity $entity,
    ): void {
        $this->validateEntityType($entity);

        $primaryKey = $this->metadata->getPrimaryKeyProperty();
        $columnName = $primaryKey?->columnName ?? 'id';

        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($this->metadata->primaryKey);
        $id = $property->getValue($entity);

        $sql = sprintf(
            'DELETE FROM %s WHERE %s = ?',
            $this->metadata->tableName,
            $columnName,
        );

        $this->eventDispatcher?->dispatch(new EntityDeleting($entity, static::ENTITY_CLASS));
        $this->connection->execute($sql, [$id]);
        $this->eventDispatcher?->dispatch(new EntityDeleted($entity, static::ENTITY_CLASS));
    }

    /**
     * Create a query builder for custom queries.
     *
     * Returns a RepositoryQueryBuilder that wraps QueryBuilderInterface
     * and provides entity hydration via getEntities() method.
     *
     * @throws RepositoryException If no query builder factory was provided
     */
    public function query(): RepositoryQueryBuilder
    {
        if ($this->queryBuilderFactory === null) {
            throw RepositoryException::queryBuilderNotConfigured(static::class);
        }

        $queryBuilder = $this->queryBuilderFactory->create();

        // Pre-configure the query builder with the table name
        $queryBuilder->table($this->metadata->tableName);

        return new RepositoryQueryBuilder(
            $queryBuilder,
            $this->hydrator,
            $this->metadata,
            static::ENTITY_CLASS,
        );
    }

    /**
     * Count all entities in the repository.
     */
    public function count(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) as aggregate FROM %s',
            $this->metadata->tableName,
        );

        $result = $this->connection->query($sql);

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    /**
     * Check if an entity with the given ID exists.
     */
    public function exists(
        int $id,
    ): bool {
        return $this->find(id: $id) !== null;
    }

    /**
     * Check if any entity matches the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     */
    public function existsBy(
        array $criteria,
    ): bool {
        return $this->findOneBy(criteria: $criteria) !== null;
    }

    /**
     * Check if a column value is unique, optionally excluding an entity by ID.
     */
    protected function isColumnUnique(
        string $column,
        mixed $value,
        ?int $excludeId = null,
    ): bool {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?',
            $this->metadata->tableName,
            $column,
        );
        $bindings = [$value];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $bindings[] = $excludeId;
        }

        $rows = $this->connection->query($sql, $bindings);

        return count($rows) === 0;
    }

    /**
     * Insert a new entity.
     */
    protected function insert(
        Entity $entity,
    ): void {
        $data = $this->hydrator->extract($entity, $this->metadata);

        // Remove primary key if it's auto-increment and null
        $pkProperty = $this->metadata->getPrimaryKeyProperty();
        if ($pkProperty?->isAutoIncrement === true) {
            $pkColumn = $pkProperty->columnName;
            if ($data[$pkColumn] === null) {
                unset($data[$pkColumn]);
            }
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->metadata->tableName,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $this->connection->execute($sql, array_values($data));

        // Set the generated ID on the entity
        if ($pkProperty?->isAutoIncrement === true) {
            $reflection = new ReflectionClass($entity);
            $property = $reflection->getProperty($this->metadata->primaryKey);
            $property->setValue($entity, $this->connection->lastInsertId());
        }
    }

    /**
     * Update an existing entity.
     *
     * Only dirty (changed) fields are updated to minimize database operations.
     */
    protected function update(
        Entity $entity,
    ): void {
        $primaryKey = $this->metadata->getPrimaryKeyProperty();
        $pkColumn = $primaryKey?->columnName ?? 'id';
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();

        // Get the dirty properties
        $dirtyProperties = $this->hydrator->getDirtyProperties($entity, $this->metadata);

        // If no fields are dirty, skip the update
        if (count($dirtyProperties) === 0) {
            return;
        }

        // Extract only the dirty field values
        $reflection = new ReflectionClass($entity);
        $data = [];

        foreach ($dirtyProperties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $value = $property->getValue($entity);
            $columnName = $propertyToColumn[$propertyName];
            $propMeta = $this->metadata->getProperty($propertyName);

            // Convert value to DB format
            $data[$columnName] = $this->convertToDbValue($value, $propMeta);
        }

        // Get the primary key value for the WHERE clause
        $pkPropertyName = $this->metadata->primaryKey;
        $pkProperty = $reflection->getProperty($pkPropertyName);
        $id = $pkProperty->getValue($entity);

        $setClauses = array_map(
            fn (string $column): string => "$column = ?",
            array_keys($data),
        );

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->metadata->tableName,
            implode(', ', $setClauses),
            $pkColumn,
        );

        $bindings = array_values($data);
        $bindings[] = $id;

        $this->connection->execute($sql, $bindings);
    }

    /**
     * Convert a PHP value to a database-compatible value.
     */
    private function convertToDbValue(
        mixed $value,
        ?PropertyMetadata $propMeta,
    ): mixed {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    /**
     * Validate that ENTITY_CLASS constant is defined.
     */
    private function validateEntityClass(): void
    {
        if (static::ENTITY_CLASS === '') {
            throw RepositoryException::missingEntityClass(static::class);
        }
    }

    /**
     * Validate that the entity is of the correct type.
     */
    private function validateEntityType(
        Entity $entity,
    ): void {
        $expectedClass = static::ENTITY_CLASS;
        if (!$entity instanceof $expectedClass) {
            throw RepositoryException::invalidEntityType(
                static::class,
                static::ENTITY_CLASS,
                $entity::class,
            );
        }
    }
}
