<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use BackedEnum;
use DateTimeImmutable;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use ReflectionClass;
use Throwable;

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
     * Relationships to eager-load on the next query.
     *
     * @var array<string>
     */
    private array $pendingRelationships = [];

    /**
     * @param ConnectionInterface $connection Database connection
     * @param EntityMetadataFactory $metadataFactory Factory for entity metadata
     * @param EntityHydrator $hydrator Entity hydrator
     * @param QueryBuilderFactoryInterface|null $queryBuilderFactory Optional factory that creates QueryBuilderInterface instances
     * @param EventDispatcherInterface|null $eventDispatcher Optional event dispatcher for lifecycle events
     * @param RelationshipLoader|null $relationshipLoader Optional loader for eager-loading relationships
     *
     * @throws RepositoryException
     */
    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly EntityMetadataFactory $metadataFactory,
        protected readonly EntityHydrator $hydrator,
        protected readonly ?QueryBuilderFactoryInterface $queryBuilderFactory = null,
        protected readonly ?EventDispatcherInterface $eventDispatcher = null,
        protected readonly ?RelationshipLoader $relationshipLoader = null,
    ) {
        $this->validateEntityClass();
        $this->metadata = $this->metadataFactory->parse(static::ENTITY_CLASS);
    }

    /**
     * Specify relationships to eager-load on the next query.
     *
     * Returns a cloned repository with the pending relationships set.
     *
     * @throws RepositoryException When RelationshipLoader is not configured or relationship name is unknown
     */
    public function with(string ...$relationships): static
    {
        if ($this->relationshipLoader === null) {
            throw RepositoryException::relationshipLoaderNotConfigured(static::class);
        }

        foreach ($relationships as $name) {
            $topLevel = explode('.', $name, 2)[0];

            if ($this->metadata->getRelationship($topLevel) === null) {
                throw RepositoryException::unknownRelationship(static::class, static::ENTITY_CLASS, $name);
            }
        }

        $clone = clone $this;
        $clone->pendingRelationships = array_values($relationships);

        return $clone;
    }

    /**
     * Find an entity by its primary key.
     *
     * @return TEntity|null
     */
    public function find(
        int|string $id,
    ): ?Entity {
        $columnName = $this->metadata->getPrimaryKeyProperty()->columnName;

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?',
            $this->metadata->tableName,
            $columnName,
        );

        $rows = $this->connection->query($sql, [$id]);

        if (count($rows) === 0) {
            return null;
        }

        $entity = $this->hydrator->hydrate(
            static::ENTITY_CLASS,
            $rows[0],
            $this->metadata,
        );

        $this->eagerLoadRelationships([$entity]);

        return $entity;
    }

    /**
     * Find an entity by its primary key or throw an exception.
     *
     * @return TEntity
     * @throws RepositoryException When entity is not found
     */
    public function findOrFail(
        int|string $id,
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
     * @return EntityCollection<TEntity>
     */
    public function findAll(): EntityCollection
    {
        $sql = sprintf('SELECT * FROM %s', $this->metadata->tableName);
        $rows = $this->connection->query($sql);

        $entities = array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );

        $this->eagerLoadRelationships($entities);

        return new EntityCollection($entities);
    }

    /**
     * Find entities matching the given criteria.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     * @return EntityCollection<TEntity>
     */
    public function findBy(
        array $criteria,
    ): EntityCollection {
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

        $entities = array_map(
            fn (array $row): Entity => $this->hydrator->hydrate(
                static::ENTITY_CLASS,
                $row,
                $this->metadata,
            ),
            $rows,
        );

        $this->eagerLoadRelationships($entities);

        return new EntityCollection($entities);
    }

    /**
     * Find a single entity matching the given criteria.
     *
     * @return TEntity|null
     */
    public function findOneBy(
        array $criteria,
    ): ?Entity {
        return $this->findBy($criteria)->first();
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
     * Insert multiple entities in a single multi-row INSERT statement.
     *
     * @param array<Entity> $entities
     * @throws BatchInsertException|RepositoryException
     */
    public function insertBatch(array $entities): void
    {
        if (count($entities) === 0) {
            throw BatchInsertException::emptyBatch();
        }

        $firstClass = $entities[0]::class;

        foreach ($entities as $index => $entity) {
            if ($entity::class !== $firstClass) {
                throw BatchInsertException::heterogeneousBatch($firstClass, $entity::class, $index);
            }
        }

        $this->validateEntityType($entities[0]);

        // Fire Creating events for each entity before insert
        foreach ($entities as $entity) {
            $this->eventDispatcher?->dispatch(new EntityCreating($entity, static::ENTITY_CLASS));
        }

        // Build column set from first entity
        $firstData = $this->extractBatchRow($entities[0]);
        $columns = array_keys($firstData);
        $expectedColumns = $columns;

        // Verify column consistency across the batch
        foreach ($entities as $index => $entity) {
            if ($index === 0) {
                continue;
            }

            $rowData = $this->extractBatchRow($entity);
            $rowColumns = array_keys($rowData);

            if ($rowColumns !== $expectedColumns) {
                throw BatchInsertException::columnSetMismatch($firstClass, $index);
            }
        }

        // Compile all row data
        $allRows = array_map(fn (Entity $e) => $this->extractBatchRow($e), $entities);

        // Build multi-row INSERT SQL
        $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($allRows), $placeholderRow));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->metadata->tableName,
            implode(', ', $columns),
            $placeholders,
        );

        // Flatten all row values into a single bindings array
        $bindings = [];
        foreach ($allRows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        // Wrap in transaction if none is active
        $ownsTransaction = false;
        if ($this->connection instanceof TransactionInterface && !$this->connection->inTransaction()) {
            $this->connection->beginTransaction();
            $ownsTransaction = true;
        }

        try {
            $this->connection->execute($sql, $bindings);

            // Populate auto-increment IDs (MySQL strategy: LAST_INSERT_ID + row offset)
            $pkProperty = $this->metadata->getPrimaryKeyProperty();
            if ($pkProperty?->isAutoIncrement === true) {
                $firstId = $this->connection->lastInsertId();
                $reflection = new ReflectionClass($entities[0]);

                foreach ($entities as $offset => $entity) {
                    $property = $reflection->getProperty($this->metadata->primaryKey);
                    $property->setValue($entity, $firstId + $offset);
                }
            }

            // Register original values for dirty tracking
            foreach ($entities as $entity) {
                $this->hydrator->registerOriginalValues($entity, $this->metadata);
            }

            if ($ownsTransaction) {
                $this->connection->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction) {
                $this->connection->rollback();
            }

            throw $e;
        }

        // Fire Created events for each entity after insert
        foreach ($entities as $entity) {
            $this->eventDispatcher?->dispatch(new EntityCreated($entity, static::ENTITY_CLASS));
        }
    }

    /**
     * Extract row data for a single entity, excluding auto-increment PK if null.
     *
     * @return array<string, mixed>
     */
    private function extractBatchRow(Entity $entity): array
    {
        $data = $this->hydrator->extract($entity, $this->metadata);

        $pkProperty = $this->metadata->getPrimaryKeyProperty();
        if ($pkProperty?->isAutoIncrement === true) {
            $pkColumn = $pkProperty->columnName;
            if ($data[$pkColumn] === null) {
                unset($data[$pkColumn]);
            }
        }

        return $data;
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

        $columnName = $this->metadata->getPrimaryKeyProperty()->columnName;

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
     * Return an EntityCollection of entities matching all given specifications.
     *
     * Specifications are applied in order to the RepositoryQueryBuilder wrapper
     * so that specs can call $builder->with() to declare eager loading.
     * Call-site relationships from with()->matching() are pre-seeded and merged
     * with any spec-declared relationships without duplicates.
     *
     * @throws RepositoryException If no query builder factory was provided
     */
    public function matching(QuerySpecification ...$specifications): EntityCollection
    {
        if ($this->queryBuilderFactory === null) {
            throw RepositoryException::queryBuilderNotConfigured(static::class);
        }

        $queryBuilder = $this->queryBuilderFactory->create();
        $queryBuilder->table($this->metadata->tableName);

        $repositoryQueryBuilder = new RepositoryQueryBuilder(
            $queryBuilder,
            $this->hydrator,
            $this->metadata,
            static::ENTITY_CLASS,
            $this->relationshipLoader,
        );

        // Pre-seed call-site relationships (from $repo->with(...)->matching(...))
        if ($this->pendingRelationships !== []) {
            $repositoryQueryBuilder->with(...$this->pendingRelationships);
        }

        foreach ($specifications as $specification) {
            $specification->apply($repositoryQueryBuilder);
        }

        return $repositoryQueryBuilder->getEntities();
    }

    /**
     * Count all entities in the repository.
     *
     * Delegates to the query builder when a factory is configured, otherwise
     * falls back to a raw SQL query. The raw-SQL path exists because Repository
     * can be constructed without a QueryBuilderFactory (e.g. in lightweight
     * contexts that only need find/save), and we must not break that contract.
     */
    public function count(): int
    {
        if ($this->queryBuilderFactory !== null) {
            return $this->query()->count();
        }

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
        int|string $id,
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
        int|string|null $excludeId = null,
    ): bool {
        $sql = sprintf(
            'SELECT * FROM %s WHERE %s = ?',
            $this->metadata->tableName,
            $column,
        );
        $bindings = [$value];

        if ($excludeId !== null) {
            $pkColumn = $this->metadata->getPrimaryKeyProperty()->columnName;
            $sql .= " AND $pkColumn != ?";
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

        $this->hydrator->registerOriginalValues($entity, $this->metadata);
    }

    /**
     * Update an existing entity.
     *
     * Only dirty (changed) fields are updated to minimize database operations.
     */
    protected function update(
        Entity $entity,
    ): void {
        $pkColumn = $this->metadata->getPrimaryKeyProperty()->columnName;
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

            // Convert value to DB format
            $data[$columnName] = $this->convertToDbValue($value);
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

        $this->hydrator->registerOriginalValues($entity, $this->metadata);
    }

    /**
     * Convert a PHP value to a database-compatible value.
     */
    private function convertToDbValue(
        mixed $value,
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
     * Eager-load any pending relationships on the given entities.
     *
     * Supports dot-notation for nested eager loading (e.g. 'comments.author').
     *
     * @param Entity[] $entities
     */
    private function eagerLoadRelationships(array $entities): void
    {
        if ($this->pendingRelationships === [] || $this->relationshipLoader === null || $entities === []) {
            return;
        }

        $tree = RelationshipLoader::parseRelationshipTree($this->pendingRelationships);
        $this->relationshipLoader->loadNested($entities, $tree, $this->metadata);
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
