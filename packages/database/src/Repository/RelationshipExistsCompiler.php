<?php

declare(strict_types=1);

namespace Marko\Database\Repository;

use Marko\Database\Entity\EntityMetadata;
use Marko\Database\Entity\EntityMetadataFactory;
use Marko\Database\Entity\RelationshipMetadata;
use Marko\Database\Entity\RelationshipType;
use Marko\Database\Exceptions\EntityException;
use Marko\Database\Exceptions\RepositoryException;
use Marko\Database\Query\QueryBuilderFactoryInterface;
use Marko\Database\Query\QueryBuilderInterface;

/**
 * Turns an entity relationship into a correlated subquery for whereHas() / whereDoesntHave().
 *
 * The subquery selects the related rows of the outer row, keyed by the relationship metadata
 * (#[HasOne], #[HasMany], #[BelongsTo], #[BelongsToMany]), so callers never restate foreign keys.
 * It is built only from QueryBuilderInterface primitives (aliased table(), whereColumn(),
 * whereExists()), so every driver compiles it without driver-specific code.
 *
 * One instance serves one top-level query and every nested whereHas() inside it: each related
 * table gets a generated alias (marko_rel_1, marko_rel_2, ...), so self-referential and nested
 * relationships never collide.
 */
class RelationshipExistsCompiler
{
    public const string ALIAS_PREFIX = 'marko_rel_';

    private int $aliasCount = 0;

    public function __construct(
        private readonly EntityMetadataFactory $metadataFactory,
        private readonly QueryBuilderFactoryInterface $queryBuilderFactory,
    ) {}

    /**
     * Assert that every segment of a dotted relationship path (e.g. "card.iteration") is declared.
     *
     * @param string $call The call being validated, for the error context
     * @throws RepositoryException When a segment does not name a relationship of its entity
     */
    public function assertRelationshipPath(
        EntityMetadata $metadata,
        string $path,
        string $call,
    ): void {
        foreach (explode('.', $path) as $segment) {
            $relationship = $this->relationship($metadata, $segment, $call);
            $metadata = $this->metadataFactory->parse($relationship->relatedClass);
        }
    }

    /**
     * Build the correlated subquery selecting the rows related to the outer row.
     *
     * $constrain receives the subquery (its table aliased), the related entity's metadata and that
     * alias, so the caller can add conditions on the related rows before the subquery is used.
     *
     * @param EntityMetadata $parentMetadata The outer query's entity
     * @param string $parentReference The outer query's table name or alias, used to qualify its columns
     * @param string $relationshipName A single relationship name declared on the outer entity
     * @param string $call The call being compiled, for the error context
     * @param callable(QueryBuilderInterface, EntityMetadata, string): void $constrain
     * @throws RepositoryException|EntityException
     */
    public function compile(
        EntityMetadata $parentMetadata,
        string $parentReference,
        string $relationshipName,
        string $call,
        callable $constrain,
    ): QueryBuilderInterface {
        $relationship = $this->relationship($parentMetadata, $relationshipName, $call);
        $relatedMetadata = $this->metadataFactory->parse($relationship->relatedClass);
        $relatedAlias = $this->nextAlias();

        $subquery = $this->queryBuilderFactory->create()
            ->table($relatedMetadata->tableName, $relatedAlias);

        match ($relationship->type) {
            RelationshipType::HasOne, RelationshipType::HasMany => $subquery->whereColumn(
                $relatedAlias . '.' . $this->column(
                    $relatedMetadata,
                    $relationship->foreignKey,
                    $parentMetadata,
                    $relationship,
                ),
                '=',
                $parentReference . '.' . $this->column(
                    $parentMetadata,
                    $parentMetadata->primaryKey,
                    $parentMetadata,
                    $relationship,
                ),
            ),
            RelationshipType::BelongsTo => $subquery->whereColumn(
                $relatedAlias . '.' . $this->column(
                    $relatedMetadata,
                    $relatedMetadata->primaryKey,
                    $parentMetadata,
                    $relationship,
                ),
                '=',
                $parentReference . '.' . $this->column(
                    $parentMetadata,
                    $relationship->foreignKey,
                    $parentMetadata,
                    $relationship,
                ),
            ),
            RelationshipType::BelongsToMany => $subquery->whereExists(
                $this->pivotSubquery(
                    $parentMetadata,
                    $parentReference,
                    $relationship,
                    $relatedMetadata,
                    $relatedAlias,
                ),
            ),
        };

        $constrain($subquery, $relatedMetadata, $relatedAlias);

        return $subquery;
    }

    /**
     * The pivot rows linking the outer row to the related row:
     * pivot.foreignKey = outer.pk AND pivot.relatedKey = related.pk.
     *
     * @throws RepositoryException|EntityException
     */
    private function pivotSubquery(
        EntityMetadata $parentMetadata,
        string $parentReference,
        RelationshipMetadata $relationship,
        EntityMetadata $relatedMetadata,
        string $relatedAlias,
    ): QueryBuilderInterface {
        if ($relationship->pivotClass === null) {
            throw EntityException::missingPivotClass($parentMetadata->entityClass, $relationship->propertyName);
        }

        $pivotMetadata = $this->metadataFactory->parse($relationship->pivotClass);
        $pivotAlias = $this->nextAlias();

        return $this->queryBuilderFactory->create()
            ->table($pivotMetadata->tableName, $pivotAlias)
            ->whereColumn(
                $pivotAlias . '.' . $this->column(
                    $pivotMetadata,
                    $relationship->foreignKey,
                    $parentMetadata,
                    $relationship,
                ),
                '=',
                $parentReference . '.' . $this->column(
                    $parentMetadata,
                    $parentMetadata->primaryKey,
                    $parentMetadata,
                    $relationship,
                ),
            )
            ->whereColumn(
                $pivotAlias . '.' . $this->column(
                    $pivotMetadata,
                    $relationship->relatedKey ?? '',
                    $parentMetadata,
                    $relationship,
                ),
                '=',
                $relatedAlias . '.' . $this->column(
                    $relatedMetadata,
                    $relatedMetadata->primaryKey,
                    $parentMetadata,
                    $relationship,
                ),
            );
    }

    /**
     * @throws RepositoryException When the entity does not declare the relationship
     */
    private function relationship(
        EntityMetadata $metadata,
        string $name,
        string $call,
    ): RelationshipMetadata {
        return $metadata->getRelationship($name) ?? throw RepositoryException::unknownRelationship(
            $metadata->entityClass,
            $name,
            array_keys($metadata->getRelationships()),
            $call,
        );
    }

    /**
     * The column a property maps to.
     *
     * @throws RepositoryException When the entity does not map the property
     */
    private function column(
        EntityMetadata $metadata,
        string $property,
        EntityMetadata $declaringMetadata,
        RelationshipMetadata $relationship,
    ): string {
        $propertyMetadata = $metadata->getProperty($property) ?? throw RepositoryException::relationshipKeyNotMapped(
            $metadata->entityClass,
            $property,
            $declaringMetadata->entityClass,
            $relationship->propertyName,
        );

        return $propertyMetadata->columnName;
    }

    private function nextAlias(): string
    {
        return self::ALIAS_PREFIX . ++$this->aliasCount;
    }
}
