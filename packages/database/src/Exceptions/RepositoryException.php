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
        int $id,
    ): self {
        return new self(
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
     * @param class-string $repositoryClass
     * @param class-string $entityClass
     */
    public static function unknownRelationship(
        string $repositoryClass,
        string $entityClass,
        string $relationshipName,
    ): self {
        return new self(
            message: "Entity '$entityClass' does not have a relationship named '$relationshipName'",
            context: "Calling with('$relationshipName') on repository '$repositoryClass'",
            suggestion: 'Check the entity class for #[HasOne], #[HasMany], or #[BelongsTo] attributes and use the correct property name',
        );
    }
}
