<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown for entity-related errors.
 */
class EntityException extends MarkoException
{
    /**
     * @param class-string $entityClass
     */
    public static function missingTableAttribute(
        string $entityClass,
    ): self {
        return new self(
            message: "Entity class '$entityClass' is missing #[Table] attribute",
            context: "Attempting to parse entity class '$entityClass' for database schema",
            suggestion: "Add #[Table('table_name')] attribute to the entity class",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function notExtendsEntity(
        string $entityClass,
    ): self {
        return new self(
            message: "Class '$entityClass' must extend Entity base class",
            context: "Attempting to parse class '$entityClass' as an entity",
            suggestion: 'Extend Marko\\Database\\Entity\\Entity in your entity class',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function noColumns(
        string $entityClass,
    ): self {
        return new self(
            message: "Entity '$entityClass' must have at least one #[Column] property",
            context: "Parsing entity '$entityClass' for database schema",
            suggestion: 'Add at least one public property with #[Column] attribute',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function autoIncrementWithoutPrimaryKey(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' has autoIncrement but is not a primary key",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: 'Either add primaryKey: true or remove autoIncrement: true from the #[Column] attribute',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function missingTypeDeclaration(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' must have a type declaration",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: "Add a type declaration to the property (e.g., public int \$$property)",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function columnAndRelationshipConflict(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' cannot have both #[Column] and a relationship attribute",
            context: "Parsing property '$property' in entity '$entityClass'",
            suggestion: "Remove #[Column] from the relationship property '$property' — relationship properties are not database columns",
        );
    }

    /**
     * @param class-string $entityClass
     * @param class-string $relatedClass
     */
    public static function relationshipEntityNotEntity(
        string $entityClass,
        string $property,
        string $relatedClass,
    ): self {
        return new self(
            message: "Relationship property '$property' in entity '$entityClass' references '$relatedClass' which does not extend Entity",
            context: "Parsing relationship '$property' in entity '$entityClass'",
            suggestion: "Ensure '$relatedClass' extends Marko\\Database\\Entity\\Entity",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function singularRelationshipTypeMismatch(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Singular relationship property '$property' in entity '$entityClass' must be a nullable Entity subclass type",
            context: "Parsing relationship '$property' in entity '$entityClass'",
            suggestion: "Change the type of '$property' to a nullable entity class, e.g., public ?RelatedEntity \$$property = null",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function collectionRelationshipTypeMismatch(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Collection relationship property '$property' in entity '$entityClass' must be typed as array or EntityCollection",
            context: "Parsing relationship '$property' in entity '$entityClass'",
            suggestion: "Change the type of '$property' to array or EntityCollection, e.g., public array \$$property = [] or public EntityCollection \$$property",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function missingPivotClass(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "BelongsToMany relationship '$property' in entity '$entityClass' has no pivot class configured",
            context: "Loading BelongsToMany relationship '$property' on entity '$entityClass'",
            suggestion: "Ensure the RelationshipMetadata for '$property' has a pivotClass set",
        );
    }

    public static function invalidJsonFromDatabase(
        mixed $value,
        string $error,
    ): self {
        return new self(
            message: "Failed to decode JSON value from database: $error",
            context: 'Hydrating a JSON column value from the database',
            suggestion: 'Ensure the database column contains valid JSON. The raw value was: ' . json_encode($value),
        );
    }

    public static function invalidJsonEncode(
        string $error,
    ): self {
        return new self(
            message: "Failed to encode PHP value to JSON for database storage: $error",
            context: 'Dehydrating a JSON column value for persistence',
            suggestion: 'Ensure the array value is JSON-serializable (no resources, closures, or non-UTF-8 strings)',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function jsonColumnTypeMismatch(
        string $entityClass,
        string $property,
        string $actualType,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' has #[Column(type: 'json')] but its PHP type is '$actualType' (must be 'array' or '?array')",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: "Change the property type to 'array' or '?array', e.g.: public array \$$property or public ?array \$$property",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function jsonColumnNullableMismatch(
        string $entityClass,
        string $property,
        bool $nullableFlag,
        bool $nullableType,
    ): self {
        $flagStr = $nullableFlag ? 'true' : 'false';
        $typeStr = $nullableType ? '?array (nullable)' : 'array (not nullable)';

        return new self(
            message: "Property '$property' in entity '$entityClass' has nullable:$flagStr on #[Column] but is typed as $typeStr — they must agree",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: "Either use #[Column(type: 'json', nullable: true)] with '?array' or #[Column(type: 'json')] with 'array'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function undefinedRelationship(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Entity '$entityClass' does not define a relationship named '$property'",
            context: "Loading relationship '$property' on entity '$entityClass'",
            suggestion: "Check that '$property' is declared with #[HasOne], #[HasMany], #[BelongsTo], or #[BelongsToMany] on the entity",
        );
    }
}
