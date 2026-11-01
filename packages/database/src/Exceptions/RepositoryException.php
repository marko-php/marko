<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for repository-related errors.
 */
class RepositoryException extends MarkoException
{
    /**
     * @param class-string $repositoryClass
     */
    public static function missingEntityClass(
        string $repositoryClass,
    ): self {
        return new self(
            message: "Repository '$repositoryClass' does not define ENTITY_CLASS constant",
            context: "Attempting to instantiate repository '$repositoryClass'",
            suggestion: "Add 'protected const string ENTITY_CLASS = YourEntity::class;' to your repository class",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function entityNotFound(
        string $entityClass,
        int|string $id,
    ): EntityNotFoundException {
        return new EntityNotFoundException(
            entityClass: $entityClass,
            id: $id,
            message: "Entity '$entityClass' with ID $id not found",
            context: 'Attempting to retrieve entity by ID',
            suggestion: 'Verify the entity exists before attempting to fetch it',
        );
    }

    /**
     * @param class-string $repositoryClass
     * @param class-string $expectedClass
     * @param class-string $actualClass
     */
    public static function invalidEntityType(
        string $repositoryClass,
        string $expectedClass,
        string $actualClass,
    ): self {
        return new self(
            message: "Repository '$repositoryClass' expects entity of type '$expectedClass', got '$actualClass'",
            context: 'Attempting to save or delete entity',
            suggestion: 'Ensure you are using the correct repository for the entity type',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function unknownProperty(
        string $entityClass,
        string $property,
        string $argument,
    ): self {
        return new self(
            message: "Entity '$entityClass' has no mapped property '$property'",
            context: "Resolving the $argument argument of Repository::upsert()",
            suggestion: 'Name entity properties (e.g. emailAddress), not column names; each must carry a #[Column] attribute',
        );
    }

    /**
     * @param class-string $repositoryClass
     */
    public static function queryBuilderNotConfigured(
        string $repositoryClass,
    ): self {
        return new self(
            message: "Repository '$repositoryClass' does not have a query builder factory configured",
            context: 'Attempting to create a custom query',
            suggestion: 'Pass a QueryBuilderFactoryInterface instance as the fourth constructor argument, or ensure the DI container has a QueryBuilderFactoryInterface binding',
        );
    }

    /**
     * @param class-string $repositoryClass
     */
    public static function relationshipLoaderNotConfigured(
        string $repositoryClass,
    ): self {
        return new self(
            message: "Repository '$repositoryClass' does not have a RelationshipLoader configured",
            context: 'Calling with() to eager-load relationships',
            suggestion: 'Pass a RelationshipLoader instance as the sixth constructor argument, or ensure the DI container has a RelationshipLoader binding',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function extenderCannotHaveRepository(
        string $entityClass,
    ): self {
        return new self(
            message: "Extender '$entityClass' has no primary key of its own and cannot have a standalone Repository. Use the parent entity's Repository.",
            context: "Constructing a Repository whose ENTITY_CLASS is '$entityClass'",
            suggestion: "Remove the Repository subclass for '$entityClass' and use the parent entity's Repository instead",
        );
    }

    /**
     * @param class-string $entityClass The entity that was expected to declare the relationship
     * @param list<string> $availableRelationships The relationship names the entity does declare
     * @param string $call The call that named it, e.g. "with('comments') on repository 'App\PostRepository'"
     */
    public static function unknownRelationship(
        string $entityClass,
        string $relationshipName,
        array $availableRelationships,
        string $call,
    ): self {
        $available = $availableRelationships === []
            ? 'It declares no relationships.'
            : 'Available relationships: ' . implode(', ', $availableRelationships) . '.';

        return new self(
            message: "Entity '$entityClass' does not have a relationship named '$relationshipName'. $available",
            context: "Calling $call",
            suggestion: 'Check the entity class for #[HasOne], #[HasMany], #[BelongsTo], or #[BelongsToMany] attributes and use the correct property name',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function relationshipFiltersNotConfigured(
        string $entityClass,
    ): self {
        return new self(
            message: "The query builder for entity '$entityClass' has no RelationshipExistsCompiler configured",
            context: 'Calling whereHas() or whereDoesntHave()',
            suggestion: 'Build the query with Repository::query() or Repository::matching(), or pass a RelationshipExistsCompiler as the sixth RepositoryQueryBuilder constructor argument',
        );
    }

    /**
     * @param class-string $entityClass The entity whose property was expected
     * @param class-string $declaringClass The entity declaring the relationship
     */
    public static function relationshipKeyNotMapped(
        string $entityClass,
        string $property,
        string $declaringClass,
        string $relationshipName,
    ): self {
        return new self(
            message: "Relationship '$relationshipName' on entity '$declaringClass' refers to property '$property', which entity '$entityClass' does not map to a column",
            context: "Compiling a whereHas()/whereDoesntHave() subquery for relationship '$relationshipName'",
            suggestion: "Add a #[Column] property '$property' to '$entityClass', or fix the key named in the relationship attribute on '$declaringClass'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function primaryKeyNotSet(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Primary key '$property' of entity '$entityClass' is not set",
            context: "Inserting a new '$entityClass'",
            suggestion: "Set '$property' before saving, or let the database generate it: give the column a default and mark it generated, e.g. #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)]",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function generatedKeyNotReadable(
        string $entityClass,
        string $property,
        string $driverName,
    ): self {
        return new self(
            message: "Primary key '$property' of entity '$entityClass' is generated by the database, but the '$driverName' connection cannot read a generated key back",
            context: "Inserting a new '$entityClass' on a connection without INSERT ... RETURNING",
            suggestion: "Set '$property' in PHP before saving, for example with a UUID from ramsey/uuid or symfony/uid. Reading generated keys back needs INSERT ... RETURNING, which PostgreSQL and MariaDB 10.5+ support and MySQL and MariaDB before 10.5 do not",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function returningRowCountMismatch(
        string $entityClass,
        string $column,
        int $expectedCount,
        int $actualCount,
    ): self {
        return new self(
            message: "INSERT ... RETURNING $column returned $actualCount row(s) for $expectedCount inserted '$entityClass' row(s)",
            context: "Reading the database-generated primary key of '$entityClass' back after an insert",
            suggestion: 'This is an unexpected database response; check for triggers or rules that rewrite inserts into the table',
        );
    }
}
