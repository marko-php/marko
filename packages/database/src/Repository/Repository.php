<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Marko\Clock\SystemClock;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\PropertyMetadata;
use Marko\Database\Entity\RelationshipLoader;
use Marko\Database\Events\EntityCreated;
use Marko\Database\Events\EntityCreating;
use Marko\Database\Events\EntityDeleted;
use Marko\Database\Events\EntityDeleting;
use Marko\Database\Events\EntityUpdated;
use Marko\Database\Events\EntityUpdating;
use Marko\Database\Exceptions\BatchInsertException;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\EntityNotFoundException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;
use Marko\Database\Query\QuerySpecification;
use Psr\Clock\ClockInterface;
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
     * @param ClockInterface $clock Clock for #[Timestamps]; the container injects the bound clock
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
        protected readonly ClockInterface $clock = new SystemClock(),
    ) {
        $this->validateEntityClass();
        $this->metadata = $this->metadataFactory->parse(static::ENTITY_CLASS);

        if ($this->metadata->isExtender()) {
            throw RepositoryException::extenderCannotHaveRepository(static::ENTITY_CLASS);
        }
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
                throw RepositoryException::unknownRelationship(
                    static::ENTITY_CLASS,
                    $topLevel,
                    array_keys($this->metadata->getRelationships()),
                    "with('$name') on repository '" . static::class . "'",
                );
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
            $this->quote($this->metadata->tableName),
            $this->quote($columnName),
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
     * @throws EntityNotFoundException When entity is not found
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
        $sql = sprintf('SELECT * FROM %s', $this->quote($this->metadata->tableName));
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
     *
     * @throws EntityException
     */
    public function findBy(
        array $criteria,
    ): EntityCollection {
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();
        $conditions = [];
        $bindings = [];

        foreach ($criteria as $property => $value) {
            $column = $propertyToColumn[$property] ?? $property;
            $conditions[] = $this->quote($column) . ' = ?';
            $bindings[] = $this->criteriaValue($property, $value);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s',
            $this->quote($this->metadata->tableName),
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
     * Issues a LIMIT 1 query so the database returns at most one row.
     * The returned entity is fully hydrated and eager-loaded, identical to findBy()->first()
     * but without fetching all matching rows first.
     *
     * @return TEntity|null
     *
     * @throws EntityException
     */
    public function findOneBy(
        array $criteria,
    ): ?Entity {
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();
        $conditions = [];
        $bindings = [];

        foreach ($criteria as $property => $value) {
            $column = $propertyToColumn[$property] ?? $property;
            $conditions[] = $this->quote($column) . ' = ?';
            $bindings[] = $this->criteriaValue($property, $value);
        }

        $sql = sprintf(
            'SELECT * FROM %s WHERE %s LIMIT 1',
            $this->quote($this->metadata->tableName),
            implode(' AND ', $conditions),
        );

        $rows = $this->connection->query($sql, $bindings);

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
     * The insert runs through transaction() when the connection supports
     * transactions, so it nests as a savepoint inside a caller's transaction.
     *
     * Generated keys: with RETURNING, keys are matched to entities by the row
     * order the server returns (observed on PostgreSQL and MariaDB 10.5+, not
     * documented by either, covered by integration tests); the row count is
     * checked. Without RETURNING (MySQL), keys are LAST_INSERT_ID() + offset *
     * @@auto_increment_increment, which assumes the batch got one consecutive
     * block (not guaranteed under innodb_autoinc_lock_mode 2 while a bulk insert
     * runs on the same table). See RepositoryInterface::insertBatch().
     *
     * @param array<Entity> $entities
     * @throws BatchInsertException|RepositoryException|Throwable
     */
    public function insertBatch(array $entities): void
    {
        $this->assertUniformBatch($entities);

        // Fire Creating events for each entity before insert
        foreach ($entities as $entity) {
            $this->eventDispatcher?->dispatch(new EntityCreating($entity, static::ENTITY_CLASS));
        }

        foreach ($entities as $entity) {
            $this->applyInsertTimestamps($entity);
        }

        $allRows = $this->extractBatchRows($entities, requireKey: true);
        $columns = array_keys($allRows[0]);
        $readsGeneratedKeys = $this->omitsGeneratedKey($allRows[0]);

        if ($readsGeneratedKeys) {
            $this->assertCanReadGeneratedKey();
        }

        // Build multi-row INSERT SQL
        $placeholderRow = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $placeholders = implode(', ', array_fill(0, count($allRows), $placeholderRow));

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $this->quote($this->metadata->tableName),
            implode(', ', array_map($this->quote(...), $columns)),
            $placeholders,
        );

        // Flatten all row values into a single bindings array
        $bindings = [];
        foreach ($allRows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        $write = function () use ($entities, $sql, $bindings, $columns, $readsGeneratedKeys): void {
            $pkProperty = $this->metadata->getPrimaryKeyProperty();
            $isAutoIncrement = $pkProperty?->isAutoIncrement === true;

            if ($pkProperty !== null
                && ($isAutoIncrement || $readsGeneratedKeys)
                && $this->connection->supportsReturning()
            ) {
                // INSERT ... RETURNING <pk> returns exact keys in insert order. On
                // PostgreSQL lastInsertId() resolves to LASTVAL() (the LAST row's id),
                // so the MySQL offset-arithmetic strategy would produce shifted ids,
                // and a generated key has no lastInsertId() at all.
                $pkColumn = $pkProperty->columnName;
                $returningRows = $this->connection->query("$sql RETURNING " . $this->quote($pkColumn), $bindings);

                $actualCount = count($returningRows);
                $expectedCount = count($entities);

                if ($actualCount !== $expectedCount) {
                    throw BatchInsertException::returningRowCountMismatch($expectedCount, $actualCount, $pkColumn);
                }

                foreach (array_values($entities) as $offset => $entity) {
                    $this->assignPrimaryKey($entity, $pkProperty, $returningRows[$offset][$pkColumn]);
                }
            } else {
                $assignsKeys = $isAutoIncrement
                    && !in_array($pkProperty->columnName, $columns, true);
                $step = $assignsKeys ? $this->readAutoIncrementStep() : 1;

                $this->connection->execute($sql, $bindings);

                // MySQL strategy: LAST_INSERT_ID() returns the FIRST inserted id for a
                // single multi-row INSERT, and each following row advances by
                // auto_increment_increment. InnoDB allocates one consecutive block for a
                // multi-row VALUES insert under every innodb_autoinc_lock_mode, unless
                // (mode 2 only) a bulk insert on the same table interleaves. The step is
                // read before the INSERT because a later statement would reset
                // lastInsertId(). Explicit keys are never overwritten.
                if ($assignsKeys) {
                    $firstId = $this->connection->lastInsertId();
                    $reflection = new ReflectionClass($entities[0]);

                    foreach ($entities as $offset => $entity) {
                        $property = $reflection->getProperty($this->metadata->primaryKey);
                        $property->setValue($entity, $firstId + $offset * $step);
                    }
                }
            }

            // Register original values for dirty tracking
            foreach ($entities as $entity) {
                $this->hydrator->registerOriginalValues($entity, $this->metadata);
            }
        };

        if ($this->connection instanceof TransactionInterface) {
            $this->connection->transaction($write);
        } else {
            $write();
        }

        // Fire Created events for each entity after insert
        foreach ($entities as $entity) {
            $this->eventDispatcher?->dispatch(new EntityCreated($entity, static::ENTITY_CLASS));
        }
    }

    /**
     * Insert the entities, or update the existing row when one already has
     * the same values in the $uniqueBy properties, in a single statement.
     *
     * $uniqueBy and $update name entity properties, never columns. The
     * conflict properties are always explicit: they must match a unique index
     * (PostgreSQL requires an index on exactly those columns). When $update is
     * null, every property except the $uniqueBy ones, the primary key and the
     * #[Timestamps] created-at property is updated; pass [] to leave existing
     * rows untouched.
     *
     * #[Timestamps] are applied first: created-at is filled when unset and
     * updated-at is set to now. Upsert does not fire lifecycle events, set
     * generated ids or register entities for dirty tracking, because it can't
     * tell which rows were inserted and which were updated. Load the entities
     * again when you need them.
     *
     * @param array<Entity> $entities
     * @param array<int, string> $uniqueBy Properties identifying an existing row
     * @param array<int, string>|null $update Properties to update on conflict
     * @return int Affected-row count as reported by the driver
     * @throws BatchInsertException|RepositoryException
     */
    public function upsert(
        array $entities,
        array $uniqueBy,
        ?array $update = null,
    ): int {
        if ($this->queryBuilderFactory === null) {
            throw RepositoryException::queryBuilderNotConfigured(static::class);
        }

        $this->assertUniformBatch($entities);

        $now = $this->now();

        foreach ($entities as $entity) {
            $this->applyInsertTimestamps($entity);
            $this->touchUpdatedAt($entity, $now);
        }

        $rows = $this->extractBatchRows($entities, requireKey: false);
        $uniqueColumns = $this->propertiesToColumns($uniqueBy, '$uniqueBy');

        if ($update === null) {
            $excluded = $uniqueColumns;
            $excluded[] = $this->metadata->getPrimaryKeyProperty()?->columnName;

            if ($this->metadata->createdAtProperty !== null) {
                $excluded[] = $this->metadata->getPropertyToColumnMap()[$this->metadata->createdAtProperty];
            }

            $updateColumns = array_values(array_filter(
                array_keys($rows[0]),
                fn (string $column): bool => !in_array($column, $excluded, true),
            ));
        } else {
            $updateColumns = $this->propertiesToColumns($update, '$update');
        }

        return $this->queryBuilderFactory->create()
            ->table($this->metadata->tableName)
            ->upsert($rows, $uniqueColumns, $updateColumns);
    }

    /**
     * Map entity property names to their column names.
     *
     * @param array<int, string> $properties
     * @return list<string>
     * @throws RepositoryException
     */
    private function propertiesToColumns(
        array $properties,
        string $argument,
    ): array {
        $map = $this->metadata->getPropertyToColumnMap();

        return array_values(array_map(
            fn (string $property): string => $map[$property]
                ?? throw RepositoryException::unknownProperty($this->metadata->entityClass, $property, $argument),
            $properties,
        ));
    }

    /**
     * Set the #[Timestamps] updated-at property to the given instant.
     */
    private function touchUpdatedAt(
        Entity $entity,
        DateTimeImmutable $now,
    ): void {
        if ($this->metadata->updatedAtProperty === null) {
            return;
        }

        new ReflectionClass($entity)->getProperty($this->metadata->updatedAtProperty)->setValue($entity, $now);
    }

    /**
     * Reject an empty batch, entities with companions, and a batch that mixes
     * entity classes or holds entities this repository does not manage.
     *
     * @param array<Entity> $entities
     * @throws BatchInsertException|RepositoryException
     */
    private function assertUniformBatch(array $entities): void
    {
        if (count($entities) === 0) {
            throw BatchInsertException::emptyBatch();
        }

        foreach ($entities as $entity) {
            if ($entity->companions() !== []) {
                throw BatchInsertException::companionsNotSupported($entity::class);
            }
        }

        $firstClass = $entities[0]::class;

        foreach ($entities as $index => $entity) {
            if ($entity::class !== $firstClass) {
                throw BatchInsertException::heterogeneousBatch($firstClass, $entity::class, $index);
            }
        }

        $this->validateEntityType($entities[0]);
    }

    /**
     * Extract one row per entity and verify every row has the same columns.
     *
     * With $requireKey, an unset key that the database does not fill throws
     * (insertBatch); upsert passes false and leaves such a key to the database.
     *
     * @param array<Entity> $entities
     * @return list<array<string, mixed>>
     * @throws BatchInsertException|EntityException|RepositoryException
     */
    private function extractBatchRows(
        array $entities,
        bool $requireKey,
    ): array {
        $rows = array_values(array_map(
            fn (Entity $entity): array => $this->withoutDatabaseFilledKey(
                $this->hydrator->extract($entity, $this->metadata),
                $requireKey,
            ),
            $entities,
        ));
        $expectedColumns = array_keys($rows[0]);
        $pkProperty = $this->metadata->getPrimaryKeyProperty();
        $firstOmitsGeneratedKey = $this->omitsGeneratedKey($rows[0]);

        foreach ($rows as $index => $row) {
            if ($pkProperty !== null && $this->omitsGeneratedKey($row) !== $firstOmitsGeneratedKey) {
                throw BatchInsertException::mixedGeneratedKeys($entities[0]::class, $pkProperty->name, $index);
            }

            if (array_keys($row) !== $expectedColumns) {
                throw BatchInsertException::columnSetMismatch($entities[0]::class, $index);
            }
        }

        return $rows;
    }

    /**
     * Leave a primary key the database fills out of an extracted row.
     *
     * An unset or null auto-increment or generated key is removed so the
     * database assigns it. Any other unset or null key throws when $requireKey
     * is true, instead of reaching the database as a NULL key.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     * @throws RepositoryException
     */
    private function withoutDatabaseFilledKey(
        array $row,
        bool $requireKey = true,
    ): array {
        $pkProperty = $this->metadata->getPrimaryKeyProperty();

        if ($pkProperty === null || ($row[$pkProperty->columnName] ?? null) !== null) {
            return $row;
        }

        if ($pkProperty->isAutoIncrement || $pkProperty->isGenerated) {
            unset($row[$pkProperty->columnName]);

            return $row;
        }

        if ($requireKey) {
            throw RepositoryException::primaryKeyNotSet($this->metadata->entityClass, $pkProperty->name);
        }

        return $row;
    }

    /**
     * Whether a row leaves out a database-generated key that must be read back.
     *
     * @param array<string, mixed> $row
     */
    private function omitsGeneratedKey(
        array $row,
    ): bool {
        $pkProperty = $this->metadata->getPrimaryKeyProperty();

        return $pkProperty?->isGenerated === true && !array_key_exists($pkProperty->columnName, $row);
    }

    /**
     * Read how far MySQL advances the auto-increment key between rows of one INSERT.
     *
     * Other drivers advance by one, so they are not asked.
     *
     * @throws BatchInsertException
     */
    private function readAutoIncrementStep(): int
    {
        if ($this->connection->driverName() !== 'mysql') {
            return 1;
        }

        $rows = $this->connection->query('SELECT @@auto_increment_increment AS auto_increment_increment');
        $value = $rows[0]['auto_increment_increment'] ?? null;

        $isInteger = is_int($value) || (is_string($value) && ctype_digit($value));

        if (!$isInteger || (int) $value < 1) {
            throw BatchInsertException::unreadableAutoIncrementStep($value);
        }

        return (int) $value;
    }

    /**
     * Fail before inserting when a generated key can't be read back from this connection.
     *
     * @throws RepositoryException
     */
    private function assertCanReadGeneratedKey(): void
    {
        if ($this->connection->supportsReturning()) {
            return;
        }

        throw RepositoryException::generatedKeyNotReadable(
            $this->metadata->entityClass,
            $this->metadata->primaryKey,
            $this->connection->driverName(),
        );
    }

    /**
     * Set a key value read back from the database, converted through the key property's cast.
     *
     * @throws EntityException
     */
    private function assignPrimaryKey(
        Entity $entity,
        PropertyMetadata $pkProperty,
        mixed $value,
    ): void {
        new ReflectionClass($entity)
            ->getProperty($pkProperty->name)
            ->setValue($entity, $this->hydrator->toPhpValue($value, $pkProperty));
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
            $this->quote($this->metadata->tableName),
            $this->quote($columnName),
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
            null,
            new RelationshipExistsCompiler($this->metadataFactory, $this->queryBuilderFactory),
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
            new RelationshipExistsCompiler($this->metadataFactory, $this->queryBuilderFactory),
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
            $this->quote($this->metadata->tableName),
        );

        $result = $this->connection->query($sql);

        return (int) ($result[0]['aggregate'] ?? 0);
    }

    /**
     * Check if an entity with the given ID exists.
     *
     * Issues a `SELECT 1 FROM <table> WHERE <pk> = ? LIMIT 1` probe —
     * no hydration, no eager-loading.
     */
    public function exists(
        int|string $id,
    ): bool {
        $columnName = $this->metadata->getPrimaryKeyProperty()->columnName;

        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s = ? LIMIT 1',
            $this->quote($this->metadata->tableName),
            $this->quote($columnName),
        );

        $rows = $this->connection->query($sql, [$id]);

        return count($rows) > 0;
    }

    /**
     * Check if any entity matches the given criteria.
     *
     * Issues a `SELECT 1 FROM <table> WHERE ... LIMIT 1` probe —
     * no hydration, no eager-loading.
     *
     * @param array<string, mixed> $criteria Column-value pairs to match
     *
     * @throws EntityException
     */
    public function existsBy(
        array $criteria,
    ): bool {
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();
        $conditions = [];
        $bindings = [];

        foreach ($criteria as $property => $value) {
            $column = $propertyToColumn[$property] ?? $property;
            $conditions[] = $this->quote($column) . ' = ?';
            $bindings[] = $this->criteriaValue($property, $value);
        }

        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s LIMIT 1',
            $this->quote($this->metadata->tableName),
            implode(' AND ', $conditions),
        );

        $rows = $this->connection->query($sql, $bindings);

        return count($rows) > 0;
    }

    /**
     * Quote a table or column name through the connection, so reserved words (`key`, `group`, `order`), mixed
     * case and embedded delimiters are safe on every driver.
     */
    private function quote(
        string $identifier,
    ): string {
        return $this->connection->quoteIdentifier($identifier);
    }

    /**
     * Convert a criteria value through the cast pipeline when the key is a mapped property.
     *
     * @throws EntityException
     */
    private function criteriaValue(
        string|int $property,
        mixed $value,
    ): mixed {
        $propertyMetadata = $this->metadata->properties[$property] ?? null;

        if ($propertyMetadata?->encrypted === true) {
            throw EntityException::encryptedCriteria($this->metadata->entityClass, (string) $property);
        }

        return $propertyMetadata !== null
            ? $this->hydrator->toDatabaseValue($value, $propertyMetadata)
            : $value;
    }

    /**
     * Check if a column value is unique, optionally excluding an entity by ID.
     *
     * Issues a `SELECT 1 FROM <table> WHERE <column> = ? [AND <pk> != ?] LIMIT 1`
     * probe — no hydration, no eager-loading.
     */
    protected function isColumnUnique(
        string $column,
        mixed $value,
        int|string|null $excludeId = null,
    ): bool {
        $sql = sprintf(
            'SELECT 1 FROM %s WHERE %s = ?',
            $this->quote($this->metadata->tableName),
            $this->quote($column),
        );
        $bindings = [$value];

        if ($excludeId !== null) {
            $pkColumn = $this->metadata->getPrimaryKeyProperty()->columnName;
            $sql .= ' AND ' . $this->quote($pkColumn) . ' != ?';
            $bindings[] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $rows = $this->connection->query($sql, $bindings);

        return count($rows) === 0;
    }

    /**
     * The current instant used for #[Timestamps]: the injected clock's time, in UTC.
     */
    protected function now(): DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * Fill unset #[Timestamps] properties with a single shared instant.
     */
    private function applyInsertTimestamps(
        Entity $entity,
    ): void {
        $now = null;
        $reflection = new ReflectionClass($entity);

        foreach ([$this->metadata->createdAtProperty, $this->metadata->updatedAtProperty] as $name) {
            if ($name === null) {
                continue;
            }

            $property = $reflection->getProperty($name);

            if (!$property->isInitialized($entity) || $property->getValue($entity) === null) {
                $property->setValue($entity, $now ??= $this->now());
            }
        }
    }

    /**
     * Insert a new entity.
     *
     * An unset or null auto-increment or generated key is left to the database.
     * A generated key is read back with INSERT ... RETURNING; on a connection
     * without RETURNING, and for any other unset key, this throws.
     *
     * @throws EntityException|RepositoryException
     */
    protected function insert(
        Entity $entity,
    ): void {
        $this->applyInsertTimestamps($entity);

        $data = $this->withoutDatabaseFilledKey($this->hydrator->extractAll($entity, $this->metadata));

        $pkProperty = $this->metadata->getPrimaryKeyProperty();
        $readsGeneratedKey = $this->omitsGeneratedKey($data);

        if ($readsGeneratedKey) {
            $this->assertCanReadGeneratedKey();
        }

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quote($this->metadata->tableName),
            implode(', ', array_map($this->quote(...), $columns)),
            implode(', ', $placeholders),
        );

        if ($readsGeneratedKey && $pkProperty !== null) {
            // The database generated the key: read it back in the same statement.
            $pkColumn = $pkProperty->columnName;
            $rows = $this->connection->query("$sql RETURNING " . $this->quote($pkColumn), array_values($data));

            if (count($rows) !== 1) {
                throw RepositoryException::returningRowCountMismatch(
                    $this->metadata->entityClass,
                    $pkColumn,
                    1,
                    count($rows),
                );
            }

            $this->assignPrimaryKey($entity, $pkProperty, $rows[0][$pkColumn]);
        } else {
            $this->connection->execute($sql, array_values($data));
        }

        // Set the generated ID on the entity
        if ($pkProperty?->isAutoIncrement === true) {
            $reflection = new ReflectionClass($entity);
            $property = $reflection->getProperty($this->metadata->primaryKey);
            $property->setValue($entity, $this->connection->lastInsertId());
        }

        $this->hydrator->registerOriginalValues($entity, $this->metadata);

        // Register original values for each freshly-attached companion so that
        // subsequent update() calls can dirty-track them correctly.
        foreach ($entity->companions() as $companion) {
            $companionMetadata = $this->metadataFactory->parse($companion::class);
            $this->hydrator->registerOriginalValues($companion, $companionMetadata);
        }
    }

    /**
     * Update an existing entity.
     *
     * Only dirty (changed) fields are updated to minimize database operations.
     * Dirty fields from attached companions are merged into the same UPDATE statement.
     */
    protected function update(
        Entity $entity,
    ): void {
        $pkColumn = $this->metadata->getPrimaryKeyProperty()->columnName;
        $propertyToColumn = $this->metadata->getPropertyToColumnMap();

        // Get the dirty properties for the parent
        $dirtyProperties = $this->hydrator->getDirtyProperties($entity, $this->metadata);

        // Extract only the dirty field values for the parent
        $reflection = new ReflectionClass($entity);
        $data = [];

        foreach ($dirtyProperties as $propertyName) {
            $property = $reflection->getProperty($propertyName);
            $value = $property->getValue($entity);
            $columnName = $propertyToColumn[$propertyName];

            $data[$columnName] = $this->hydrator->toDatabaseValue($value, $this->metadata->properties[$propertyName]);
        }

        // Collect dirty companion data. Companions with no originalValues
        // (never hydrated, rolling deploy) are skipped entirely.
        $participatingCompanions = [];

        foreach ($entity->companions() as $companion) {
            $companionMetadata = $this->metadataFactory->parse($companion::class);
            $companionOriginalValues = $this->hydrator->getOriginalValues($companion);

            // Never hydrated and no original values — skip (rolling deploy)
            if ($companionOriginalValues === []) {
                continue;
            }

            $companionDirtyProperties = $this->hydrator->getDirtyProperties($companion, $companionMetadata);

            if ($companionDirtyProperties === []) {
                $participatingCompanions[] = [$companion, $companionMetadata];
                continue;
            }

            $companionPropertyToColumn = $companionMetadata->getPropertyToColumnMap();
            $companionReflection = new ReflectionClass($companion);

            foreach ($companionDirtyProperties as $propertyName) {
                $property = $companionReflection->getProperty($propertyName);
                $value = $property->getValue($companion);
                $columnName = $companionPropertyToColumn[$propertyName];

                $data[$columnName] = $this->hydrator->toDatabaseValue(
                    $value,
                    $companionMetadata->properties[$propertyName],
                );
            }

            $participatingCompanions[] = [$companion, $companionMetadata];
        }

        // If no fields are dirty (parent + companions), skip the update
        if ($data === []) {
            return;
        }

        $updatedAt = $this->metadata->updatedAtProperty;

        if ($updatedAt !== null && !in_array($updatedAt, $dirtyProperties, true)) {
            $now = $this->now();
            $reflection->getProperty($updatedAt)->setValue($entity, $now);
            $updatedAtMetadata = $this->metadata->properties[$updatedAt];
            $data[$updatedAtMetadata->columnName] = $this->hydrator->toDatabaseValue($now, $updatedAtMetadata);
        }

        // Get the primary key value for the WHERE clause
        $pkPropertyName = $this->metadata->primaryKey;
        $pkProperty = $reflection->getProperty($pkPropertyName);
        $id = $pkProperty->getValue($entity);

        $setClauses = array_map(
            fn (string $column): string => $this->quote($column) . ' = ?',
            array_keys($data),
        );

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = ?',
            $this->quote($this->metadata->tableName),
            implode(', ', $setClauses),
            $this->quote($pkColumn),
        );

        $bindings = array_values($data);
        $bindings[] = $id;

        $this->connection->execute($sql, $bindings);

        $this->hydrator->registerOriginalValues($entity, $this->metadata);

        // Refresh original values snapshots for participating companions
        foreach ($participatingCompanions as [$companion, $companionMetadata]) {
            $this->hydrator->registerOriginalValues($companion, $companionMetadata);
        }
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
