<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Exceptions\RepositoryException;
use ReflectionClass;

/**
 * Abstract base class for entity repositories.
 *
 * Concrete repositories must define the ENTITY_CLASS constant
 * specifying which entity class they manage.
 */
abstract class Repository implements RepositoryInterface
{
    /**
     * The entity class this repository manages.
     * Must be defined in concrete repository classes.
     */
    protected const string ENTITY_CLASS = '';

    protected readonly EntityMetadata $metadata;

    public function __construct(
        protected readonly ConnectionInterface $connection,
        protected readonly EntityMetadataFactory $metadataFactory,
        protected readonly EntityHydrator $hydrator,
    ) {
        $this->validateEntityClass();
        $this->metadata = $this->metadataFactory->parse(static::ENTITY_CLASS);
    }

    /**
     * Find an entity by its primary key.
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
     * Find all entities in the repository.
     *
     * @return array<Entity>
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
     * @return array<Entity>
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
     */
    public function findOneBy(
        array $criteria,
    ): ?Entity {
        $results = $this->findBy($criteria);

        return $results[0] ?? null;
    }

    /**
     * Save an entity (insert or update).
     */
    public function save(
        Entity $entity,
    ): void {
        $this->validateEntityType($entity);

        if ($this->hydrator->isNew($entity, $this->metadata)) {
            $this->insert($entity);
        } else {
            $this->update($entity);
        }
    }

    /**
     * Delete an entity.
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

        $this->connection->execute($sql, [$id]);
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
     */
    protected function update(
        Entity $entity,
    ): void {
        $data = $this->hydrator->extract($entity, $this->metadata);
        $primaryKey = $this->metadata->getPrimaryKeyProperty();
        $pkColumn = $primaryKey?->columnName ?? 'id';

        // Remove primary key from update data
        $id = $data[$pkColumn];
        unset($data[$pkColumn]);

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
        if (!$entity instanceof static::ENTITY_CLASS) {
            throw RepositoryException::invalidEntityType(
                static::class,
                static::ENTITY_CLASS,
                $entity::class,
            );
        }
    }
}
