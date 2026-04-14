<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use Marko\Database\Exceptions\EntityException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use ReflectionClass;

/**
 * Batch eager-loads entity relationships using WHERE IN queries to prevent N+1 problems.
 */
readonly class RelationshipLoader
{
    public function __construct(
        private EntityMetadataFactory $entityMetadataFactory,
        private EntityHydrator $entityHydrator,
        private QueryBuilderFactoryInterface $queryBuilderFactory,
    ) {}

    /**
     * Parse a flat list of dot-notation relationship strings into a nested tree.
     *
     * For example, ['comments.author', 'comments.tags', 'category'] becomes:
     * ['comments' => ['author' => [], 'tags' => []], 'category' => []]
     *
     * @param string[] $relationships
     * @return array<string, mixed>
     */
    public static function parseRelationshipTree(array $relationships): array
    {
        $tree = [];

        foreach ($relationships as $relationship) {
            $parts = explode('.', $relationship, 2);
            $parent = $parts[0];
            $rest = $parts[1] ?? null;

            if (!isset($tree[$parent])) {
                $tree[$parent] = [];
            }

            if ($rest !== null) {
                $nested = self::parseRelationshipTree([$rest]);

                foreach ($nested as $key => $children) {
                    if (!isset($tree[$parent][$key])) {
                        $tree[$parent][$key] = [];
                    }

                    if ($children !== []) {
                        $tree[$parent][$key] = array_merge_recursive($tree[$parent][$key], $children);
                    }
                }
            }
        }

        return $tree;
    }

    /**
     * Load a nested tree of relationships on a set of parent entities.
     *
     * Processes top-level relationships first, then recursively loads child
     * relationships on the collected results — each level batched into a
     * single query to prevent N+1 problems.
     *
     * @param Entity[] $entities
     * @param array<string, mixed> $relationshipTree
     */
    public function loadNested(
        array $entities,
        array $relationshipTree,
        EntityMetadata $parentMetadata,
    ): void {
        if ($entities === [] || $relationshipTree === []) {
            return;
        }

        foreach ($relationshipTree as $name => $children) {
            $relationship = $parentMetadata->getRelationship($name);

            if ($relationship === null) {
                continue;
            }

            $this->load($entities, $relationship, $parentMetadata);

            if ($children === []) {
                continue;
            }

            // Collect all related entities from this level for recursive loading
            $relatedEntities = [];

            foreach ($entities as $entity) {
                $reflection = new ReflectionClass($entity);
                $property = $reflection->getProperty($relationship->propertyName);

                if (!$property->isInitialized($entity)) {
                    continue;
                }

                $value = $property->getValue($entity);

                if ($value === null) {
                    continue;
                }

                if (is_array($value)) {
                    foreach ($value as $related) {
                        $relatedEntities[] = $related;
                    }
                } else {
                    $relatedEntities[] = $value;
                }
            }

            if ($relatedEntities === []) {
                continue;
            }

            $relatedMetadata = $this->entityMetadataFactory->parse($relationship->relatedClass);
            $this->loadNested($relatedEntities, $children, $relatedMetadata);
        }
    }

    /**
     * Load a relationship for a set of parent entities in a single batch query.
     *
     * @param Entity[] $entities The parent entities to load the relationship for
     * @param RelationshipMetadata $relationship The relationship to load
     * @param EntityMetadata $parentMetadata The metadata for the parent entity class
     * @throws EntityException
     */
    public function load(
        array $entities,
        RelationshipMetadata $relationship,
        EntityMetadata $parentMetadata,
    ): void {
        if ($relationship->type === RelationshipType::BelongsToMany && $relationship->pivotClass === null) {
            throw EntityException::missingPivotClass($parentMetadata->entityClass, $relationship->propertyName);
        }

        match ($relationship->type) {
            RelationshipType::BelongsTo => $this->loadBelongsTo($entities, $relationship, $parentMetadata),
            RelationshipType::HasOne => $this->loadHasOne($entities, $relationship, $parentMetadata),
            RelationshipType::HasMany => $this->loadHasMany($entities, $relationship, $parentMetadata),
            RelationshipType::BelongsToMany => $this->loadBelongsToMany($entities, $relationship, $parentMetadata),
        };
    }

    /**
     * Load a BelongsTo relationship.
     * The FK property is on the PARENT entity; we query related table WHERE pk IN (fk_values).
     *
     * @param Entity[] $entities
     */
    private function loadBelongsTo(
        array $entities,
        RelationshipMetadata $relationship,
        EntityMetadata $parentMetadata,
    ): void {
        $fkProperty = $parentMetadata->getProperty($relationship->foreignKey);

        if ($fkProperty === null) {
            return;
        }

        $relatedMetadata = $this->entityMetadataFactory->parse($relationship->relatedClass);
        $relatedPkProperty = $relatedMetadata->getPrimaryKeyProperty();

        if ($relatedPkProperty === null) {
            return;
        }

        $fkValues = $this->collectPropertyValues($entities, $relationship->foreignKey);
        $fkValues = array_values(array_unique(array_filter($fkValues, fn ($v) => $v !== null)));

        if ($fkValues === []) {
            $this->setPropertyOnAll($entities, $relationship->propertyName, null);

            return;
        }

        $rows = $this->queryBuilderFactory->create()
            ->table($relatedMetadata->tableName)
            ->whereIn($relatedPkProperty->columnName, $fkValues)
            ->get();

        $relatedByPk = [];

        foreach ($rows as $row) {
            $entity = $this->entityHydrator->hydrate($relationship->relatedClass, $row, $relatedMetadata);
            $pkValue = $row[$relatedPkProperty->columnName];
            $relatedByPk[$pkValue] = $entity;
        }

        foreach ($entities as $entity) {
            $fkValue = $this->getPropertyValue($entity, $relationship->foreignKey);
            $related = $fkValue !== null ? ($relatedByPk[$fkValue] ?? null) : null;
            $this->setProperty($entity, $relationship->propertyName, $related);
        }
    }

    /**
     * Load a HasOne relationship.
     * The FK property is on the RELATED entity; query related table WHERE fk IN (parent_pk_values).
     *
     * @param Entity[] $entities
     */
    private function loadHasOne(
        array $entities,
        RelationshipMetadata $relationship,
        EntityMetadata $parentMetadata,
    ): void {
        $parentPkProperty = $parentMetadata->getPrimaryKeyProperty();

        if ($parentPkProperty === null) {
            return;
        }

        $relatedMetadata = $this->entityMetadataFactory->parse($relationship->relatedClass);
        $relatedFkProperty = $relatedMetadata->getProperty($relationship->foreignKey);

        if ($relatedFkProperty === null) {
            return;
        }

        $pkValues = $this->collectPropertyValues($entities, $parentMetadata->primaryKey);
        $pkValues = array_values(array_unique(array_filter($pkValues, fn ($v) => $v !== null)));

        if ($pkValues === []) {
            $this->setPropertyOnAll($entities, $relationship->propertyName, null);

            return;
        }

        $rows = $this->queryBuilderFactory->create()
            ->table($relatedMetadata->tableName)
            ->whereIn($relatedFkProperty->columnName, $pkValues)
            ->get();

        $relatedByFk = [];

        foreach ($rows as $row) {
            $entity = $this->entityHydrator->hydrate($relationship->relatedClass, $row, $relatedMetadata);
            $fkValue = $row[$relatedFkProperty->columnName];
            $relatedByFk[$fkValue] = $entity;
        }

        foreach ($entities as $entity) {
            $pkValue = $this->getPropertyValue($entity, $parentMetadata->primaryKey);
            $related = $pkValue !== null ? ($relatedByFk[$pkValue] ?? null) : null;
            $this->setProperty($entity, $relationship->propertyName, $related);
        }
    }

    /**
     * Load a HasMany relationship.
     * The FK property is on the RELATED entity; query related table WHERE fk IN (parent_pk_values).
     *
     * @param Entity[] $entities
     */
    private function loadHasMany(
        array $entities,
        RelationshipMetadata $relationship,
        EntityMetadata $parentMetadata,
    ): void {
        $parentPkProperty = $parentMetadata->getPrimaryKeyProperty();

        if ($parentPkProperty === null) {
            return;
        }

        $relatedMetadata = $this->entityMetadataFactory->parse($relationship->relatedClass);
        $relatedFkProperty = $relatedMetadata->getProperty($relationship->foreignKey);

        if ($relatedFkProperty === null) {
            return;
        }

        $pkValues = $this->collectPropertyValues($entities, $parentMetadata->primaryKey);
        $pkValues = array_values(array_unique(array_filter($pkValues, fn ($v) => $v !== null)));

        if ($pkValues === []) {
            $this->setPropertyOnAll($entities, $relationship->propertyName, []);

            return;
        }

        $rows = $this->queryBuilderFactory->create()
            ->table($relatedMetadata->tableName)
            ->whereIn($relatedFkProperty->columnName, $pkValues)
            ->get();

        $groupedByFk = [];

        foreach ($rows as $row) {
            $entity = $this->entityHydrator->hydrate($relationship->relatedClass, $row, $relatedMetadata);
            $fkValue = $row[$relatedFkProperty->columnName];
            $groupedByFk[$fkValue][] = $entity;
        }

        foreach ($entities as $entity) {
            $pkValue = $this->getPropertyValue($entity, $parentMetadata->primaryKey);
            $related = $pkValue !== null ? ($groupedByFk[$pkValue] ?? []) : [];
            $this->setProperty($entity, $relationship->propertyName, $related);
        }
    }

    /**
     * Load a BelongsToMany relationship through a pivot entity table.
     * Uses exactly 2 queries: one for the pivot table, one for the related table.
     *
     * @param Entity[] $entities
     */
    private function loadBelongsToMany(
        array $entities,
        RelationshipMetadata $relationship,
        EntityMetadata $parentMetadata,
    ): void {
        $parentPkProperty = $parentMetadata->getPrimaryKeyProperty();

        if ($parentPkProperty === null) {
            return;
        }

        $pivotMetadata = $this->entityMetadataFactory->parse($relationship->pivotClass);
        $fkProperty = $pivotMetadata->getProperty($relationship->foreignKey);
        $relatedKeyProperty = $pivotMetadata->getProperty($relationship->relatedKey);

        if ($fkProperty === null || $relatedKeyProperty === null) {
            return;
        }

        $parentPkValues = $this->collectPropertyValues($entities, $parentMetadata->primaryKey);
        $parentPkValues = array_values(array_unique(array_filter($parentPkValues, fn ($v) => $v !== null)));

        if ($parentPkValues === []) {
            $this->setPropertyOnAll($entities, $relationship->propertyName, []);

            return;
        }

        // Query 1: pivot table WHERE fk_column IN (parent_pk_values)
        $pivotRows = $this->queryBuilderFactory->create()
            ->table($pivotMetadata->tableName)
            ->whereIn($fkProperty->columnName, $parentPkValues)
            ->get();

        if ($pivotRows === []) {
            $this->setPropertyOnAll($entities, $relationship->propertyName, []);

            return;
        }

        // Collect related IDs from pivot rows, deduplicated
        $relatedIds = array_values(array_unique(array_map(
            fn (array $row) => $row[$relatedKeyProperty->columnName],
            $pivotRows,
        )));

        $relatedMetadata = $this->entityMetadataFactory->parse($relationship->relatedClass);
        $relatedPkProperty = $relatedMetadata->getPrimaryKeyProperty();

        if ($relatedPkProperty === null) {
            return;
        }

        // Query 2: related table WHERE pk_column IN (related_ids)
        $relatedRows = $this->queryBuilderFactory->create()
            ->table($relatedMetadata->tableName)
            ->whereIn($relatedPkProperty->columnName, $relatedIds)
            ->get();

        // Index related entities by their PK
        $relatedByPk = [];

        foreach ($relatedRows as $row) {
            $entity = $this->entityHydrator->hydrate($relationship->relatedClass, $row, $relatedMetadata);
            $pkValue = $row[$relatedPkProperty->columnName];
            $relatedByPk[$pkValue] = $entity;
        }

        // Build a map: parent PK => [related entity, ...]
        $groupedByParentPk = [];

        foreach ($pivotRows as $pivotRow) {
            $fkValue = $pivotRow[$fkProperty->columnName];
            $relatedId = $pivotRow[$relatedKeyProperty->columnName];

            if (isset($relatedByPk[$relatedId])) {
                $groupedByParentPk[$fkValue][] = $relatedByPk[$relatedId];
            }
        }

        foreach ($entities as $entity) {
            $pkValue = $this->getPropertyValue($entity, $parentMetadata->primaryKey);
            $related = $pkValue !== null ? ($groupedByParentPk[$pkValue] ?? []) : [];
            $this->setProperty($entity, $relationship->propertyName, $related);
        }
    }

    /**
     * Collect the value of a property from all entities.
     *
     * @param Entity[] $entities
     * @return array<mixed>
     */
    private function collectPropertyValues(array $entities, string $propertyName): array
    {
        return array_map(fn (Entity $entity) => $this->getPropertyValue($entity, $propertyName), $entities);
    }

    /**
     * Get a property value from an entity via reflection.
     */
    private function getPropertyValue(Entity $entity, string $propertyName): mixed
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($propertyName);

        if (!$property->isInitialized($entity)) {
            return null;
        }

        return $property->getValue($entity);
    }

    /**
     * Set a property value on an entity via reflection.
     */
    private function setProperty(Entity $entity, string $propertyName, mixed $value): void
    {
        $reflection = new ReflectionClass($entity);
        $property = $reflection->getProperty($propertyName);
        $property->setValue($entity, $value);
    }

    /**
     * Set a property value on all entities via reflection.
     *
     * @param Entity[] $entities
     */
    private function setPropertyOnAll(array $entities, string $propertyName, mixed $value): void
    {
        foreach ($entities as $entity) {
            $this->setProperty($entity, $propertyName, $value);
        }
    }
}
