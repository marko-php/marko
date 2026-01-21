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
            suggestion: 'Pass a query builder factory closure as the fourth constructor argument',
        );
    }

    /**
     * @param class-string $repositoryClass
     */
    public static function invalidQueryBuilder(
        string $repositoryClass,
    ): self {
        return new self(
            message: "Query builder factory for '$repositoryClass' did not return a QueryBuilderInterface instance",
            context: 'Creating a query builder from factory',
            suggestion: 'Ensure the factory closure returns an instance of QueryBuilderInterface',
        );
    }
}
