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
    public static function missingNameAndExtends(
        string $entityClass,
    ): self {
        return new self(
            message: "Entity '$entityClass' #[Table] attribute requires either name: or extends: to be set",
            context: "Parsing #[Table] attribute on entity class '$entityClass'",
            suggestion: "Add a table name via #[Table('table_name')] or use #[Table(extends: ParentEntity::class)] for an extender entity",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function extenderDeclaresOwnName(
        string $entityClass,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' declares its own name: on #[Table], which is not allowed",
            context: "Parsing #[Table] attribute on extender entity '$entityClass'",
            suggestion: 'Remove name: from the #[Table] attribute — extenders inherit the table name from their parent',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function extenderDeclaresPrimaryKey(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' declares a primaryKey column '$property', which is not allowed",
            context: "Parsing column '$property' in extender entity '$entityClass'",
            suggestion: 'Remove primaryKey: true from the #[Column] attribute — extenders inherit the primary key from their parent entity',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function extenderDeclaresAutoIncrement(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' declares an autoIncrement column '$property', which is not allowed",
            context: "Parsing column '$property' in extender entity '$entityClass'",
            suggestion: 'Remove autoIncrement: true from the #[Column] attribute — extenders inherit the primary key from their parent entity',
        );
    }

    /**
     * @param class-string $entityClass
     * @param class-string $parentClass
     */
    public static function extenderParentClassNotFound(
        string $entityClass,
        string $parentClass,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' references parent class '$parentClass' which does not exist",
            context: "Parsing extends: on entity '$entityClass'",
            suggestion: "Ensure '$parentClass' is a valid, autoloadable class name",
        );
    }

    /**
     * @param class-string $entityClass
     * @param class-string $parentClass
     */
    public static function extenderParentNotEntity(
        string $entityClass,
        string $parentClass,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' extends '$parentClass' which does not extend Entity",
            context: "Parsing extends: on entity '$entityClass'",
            suggestion: "Ensure '$parentClass' extends Marko\\Database\\Entity\\Entity",
        );
    }

    /**
     * @param class-string $entityClass
     * @param class-string $parentClass
     */
    public static function chainedExtensionNotSupported(
        string $entityClass,
        string $parentClass,
    ): self {
        return new self(
            message: "Chained extension is not supported. $entityClass's parent $parentClass is itself an extender. Extend the root entity directly.",
            context: "Parsing extends: on entity '$entityClass'",
            suggestion: "Change extends: to point at the root entity instead of '$parentClass'",
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

    /**
     * @param class-string $entityClass
     */
    public static function hydratorRequiresMetadataFactory(
        string $entityClass,
    ): self {
        return new self(
            message: "Entity '$entityClass' has extenders but EntityHydrator was constructed without an EntityMetadataFactory",
            context: "Calling EntityHydrator::hydrate() for entity '$entityClass' which has linked extenders",
            suggestion: 'Inject an EntityMetadataFactory into the EntityHydrator constructor: new EntityHydrator($metadataFactory)',
        );
    }

    /**
     * @param class-string $classA
     * @param class-string $classB
     */
    public static function duplicateColumnInExtender(
        string $columnName,
        string $classA,
        string $classB,
    ): self {
        return new self(
            message: "Column '$columnName' is defined by both '$classA' and '$classB'",
            context: 'Merging extender columns into the parent table schema',
            suggestion: 'Rename the column in one of the extenders to avoid the conflict',
        );
    }

    /**
     * @param class-string $classA
     * @param class-string $classB
     */
    public static function duplicateIndexInExtender(
        string $indexName,
        string $classA,
        string $classB,
    ): self {
        return new self(
            message: "Index '$indexName' is defined by both '$classA' and '$classB'",
            context: 'Merging extender indexes into the parent table schema',
            suggestion: 'Rename the index in one of the extenders to avoid the conflict',
        );
    }

    /**
     * @param class-string $extenderClass
     * @param class-string $parentClass
     */
    public static function extenderRegisteredWithoutParent(
        string $extenderClass,
        string $parentClass,
    ): self {
        return new self(
            message: "Extender '$extenderClass' references parent '$parentClass' which was not included in the registration set",
            context: "Calling registerEntities() with extender '$extenderClass'",
            suggestion: "Include '$parentClass' in the same registerEntities() call, or use registerEntities() instead of registerEntity() for extender/parent pairs",
        );
    }
}
