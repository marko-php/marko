<?php

declare(strict_types=1);

namespace Marko\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;
use Marko\Database\Schema\IdentifierName;
use Throwable;

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
    public static function generatedWithoutPrimaryKey(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' is generated but is not a primary key",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: 'Only a primary key can be generated: add primaryKey: true or remove generated: true from the #[Column] attribute',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function generatedWithAutoIncrement(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' is both generated and autoIncrement",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: 'Use autoIncrement: true for an integer key from a sequence, or generated: true for a key from an expression default such as gen_random_uuid(), not both',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function generatedWithoutDefault(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' is generated but has no default to generate it",
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: "Add the database expression that generates the key, e.g. #[Column(primaryKey: true, type: 'uuid', default: 'gen_random_uuid()', generated: true)], or remove generated: true and set the key in PHP",
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
    public static function unionTypeRequiresColumnType(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' in entity '$entityClass' has a union type "
                . 'and must declare an explicit column type',
            context: "Parsing column '$property' in entity '$entityClass'",
            suggestion: "Add an explicit type to the #[Column] attribute (e.g., #[Column(type: 'varchar')])",
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
     * @param class-string $entityClass
     */
    public static function foreignKeyWithoutColumns(
        string $entityClass,
        string $referencedTable,
    ): self {
        return new self(
            message: "#[ForeignKey] on entity '$entityClass' referencing table '$referencedTable' declares no columns",
            context: "While parsing #[ForeignKey] attributes of entity '$entityClass'",
            suggestion: "List the local columns, for example #[ForeignKey(columns: ['user_id'], references: '$referencedTable', referencedColumns: ['id'])]",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function foreignKeyColumnCountMismatch(
        string $entityClass,
        string $foreignKeyName,
        int $columnCount,
        int $referencedColumnCount,
    ): self {
        return new self(
            message: "Foreign key $foreignKeyName on entity '$entityClass' lists $columnCount column(s) but references $referencedColumnCount column(s)",
            context: "While parsing #[ForeignKey] attributes of entity '$entityClass'. Each local column pairs with one referenced column",
            suggestion: "Make 'columns' and 'referencedColumns' the same length, in matching order",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function unknownAttributeColumn(
        string $entityClass,
        string $attribute,
        string $declarationName,
        string $column,
        ?string $suggestedColumn,
    ): self {
        $suggestion = $suggestedColumn !== null
            ? "'$column' is a property name, use the column name '$suggestedColumn'"
            : "Name one of the columns declared with #[Column] on '$entityClass'. $attribute lists database column names";

        return new self(
            message: "$attribute $declarationName on entity '$entityClass' names the column '$column', which the entity does not have",
            context: "While parsing $attribute attributes of entity '$entityClass'",
            suggestion: $suggestion,
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function indexNameTooLong(
        string $entityClass,
        string $tableName,
        string $indexName,
        int $byteLength,
    ): self {
        $maxBytes = IdentifierName::MAX_BYTES;

        return new self(
            message: "Index name '$indexName' declared by entity '$entityClass' is $byteLength bytes, over the $maxBytes bytes limit",
            context: "Building the schema of table '$tableName' from #[Index] on entity '$entityClass'. PostgreSQL silently truncates a longer name and MySQL rejects it",
            suggestion: "Shorten #[Index(name: '...')] to $maxBytes bytes or fewer. Multibyte characters count as more than one byte",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function foreignKeyNameTooLong(
        string $entityClass,
        string $tableName,
        string $foreignKeyName,
        int $byteLength,
    ): self {
        $maxBytes = IdentifierName::MAX_BYTES;

        return new self(
            message: "Foreign key name '$foreignKeyName' declared by entity '$entityClass' is $byteLength bytes, over the $maxBytes bytes limit",
            context: "Building the schema of table '$tableName' from #[ForeignKey] on entity '$entityClass'. PostgreSQL silently truncates a longer name and MySQL rejects it",
            suggestion: "Shorten #[ForeignKey(name: '...')] to $maxBytes bytes or fewer. Multibyte characters count as more than one byte",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function duplicateForeignKeyName(
        string $entityClass,
        string $tableName,
        string $foreignKeyName,
    ): self {
        return new self(
            message: "Two foreign keys of table '$tableName' resolve to the name '$foreignKeyName'",
            context: "Building the schema of table '$tableName' from #[ForeignKey] and #[Column(references:)] on entity '$entityClass'. A foreign key without a name derives fk_<table>_<columns>, the name #[Column(references:)] gets for a single column",
            suggestion: "Give one of them an explicit name, for example #[ForeignKey([...], '...', [...], name: '...')]",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function foreignKeyDeclaredTwice(
        string $entityClass,
        string $tableName,
        string $column,
    ): self {
        return new self(
            message: "Column '$column' of table '$tableName' is given a foreign key both by #[Column(references:)] and by #[ForeignKey]",
            context: "Building the schema of table '$tableName' on entity '$entityClass'",
            suggestion: "Keep one: drop references: from the column, or drop the #[ForeignKey] that lists only '$column'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function malformedColumnReference(
        string $entityClass,
        string $columnName,
        string $references,
    ): self {
        return new self(
            message: "Column '$columnName' of entity '$entityClass' has references: '$references', which is not in table.column format",
            context: "Building the foreign keys of entity '$entityClass' from #[Column(references:)]. Only a table and a column are accepted, so a schema-qualified value such as 'public.users.id' is rejected",
            suggestion: "Write references: 'table.column', for example references: 'users.id'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function duplicateIndexName(
        string $entityClass,
        string $tableName,
        string $indexName,
    ): self {
        return new self(
            message: "Two indexes of table '$tableName' resolve to the name '$indexName'",
            context: "Building the schema of table '$tableName' from #[Index] on entity '$entityClass'. Indexes on the same columns with the same uniqueness derive the same name",
            suggestion: "Give one of them an explicit name, for example #[Index(['...'], name: '...')]",
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
     * @param class-string $classA
     * @param class-string $classB
     */
    public static function duplicateForeignKeyInExtender(
        string $foreignKeyName,
        string $classA,
        string $classB,
    ): self {
        return new self(
            message: "Foreign key '$foreignKeyName' is defined by both '$classA' and '$classB'",
            context: 'Merging extender foreign keys into the parent table schema',
            suggestion: "Give one of them an explicit name, for example #[ForeignKey([...], '...', [...], name: '...')]",
        );
    }

    /**
     * @param class-string $classA
     * @param class-string $classB
     */
    public static function nameSharedAcrossTables(
        string $kind,
        string $name,
        string $tableA,
        string $classA,
        string $tableB,
        string $classB,
    ): self {
        return new self(
            message: "$kind '$name' is declared by both table '$tableA' ('$classA') and table '$tableB' ('$classB')",
            context: 'Registering entity schemas. MySQL scopes foreign key names to the database, and PostgreSQL and SQLite scope index names to the schema or database, so the name must be unique across tables on every driver',
            suggestion: 'Rename the one with an explicit name: or drop its name: to get a table-scoped derived name. When both names are derived, give one an explicit name:',
        );
    }

    /**
     * @param class-string $foreignKeyClass
     * @param class-string $indexClass
     */
    public static function foreignKeyAndIndexShareName(
        string $tableName,
        string $name,
        string $foreignKeyClass,
        string $indexClass,
    ): self {
        return new self(
            message: "Foreign key '$name' of '$foreignKeyClass' and index '$name' of '$indexClass' on table '$tableName' share a name but cover different columns",
            context: "Registering the schema of table '$tableName'. MySQL gives a foreign key's automatically created index the constraint's name",
            suggestion: 'Rename one of them with an explicit name:, or drop the name: of one to get a table-scoped derived name',
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

    public static function castClassInvalid(
        string $castClass,
        string $context,
    ): self {
        return new self(
            message: "Cast class '$castClass' must implement Marko\\Database\\Entity\\Cast\\CastInterface",
            context: $context,
            suggestion: "Make '$castClass' implement CastInterface (toPhp() and toDatabase()), or point #[Cast] at a class that does",
        );
    }

    public static function castRequiresContainer(
        string $castClass,
    ): self {
        return new self(
            message: "Cast class '$castClass' requires constructor dependencies but no container is available to build it",
            context: "Resolving cast '$castClass' through a CastResolver constructed without a ContainerInterface",
            suggestion: 'Construct the CastResolver with the application container (new CastResolver($container)), or resolve EntityHydrator from the container',
        );
    }

    public static function castResolutionFailed(
        string $castClass,
        Throwable $previous,
    ): self {
        return new self(
            message: "Failed to build cast '$castClass': {$previous->getMessage()}",
            context: "Resolving cast '$castClass' through the container",
            suggestion: "Check that every constructor dependency of '$castClass' is bound in the container",
            previous: $previous,
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function castAndEncryptedConflict(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' on entity '$entityClass' declares both #[Cast] and #[Encrypted]",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: 'Use one of them. #[Encrypted] already applies the built-in conversion for the property type before encrypting',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptionNotInstalled(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' on entity '$entityClass' is #[Encrypted] but marko/encryption is not installed",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: 'Install an encryption driver: composer require marko/encryption-openssl',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptorNotBound(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Property '$property' on entity '$entityClass' is #[Encrypted] but no EncryptorInterface implementation is bound",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: 'Install an encryption driver: composer require marko/encryption-openssl',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptedColumnTypeMismatch(
        string $entityClass,
        string $property,
        string $columnType,
    ): self {
        return new self(
            message: "Encrypted property '$property' on entity '$entityClass' declares column type '$columnType'",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: "Ciphertext is stored in a text column. Remove type: '$columnType' from #[Column] or use type: 'text'",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptedPrimaryKey(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Primary key property '$property' on entity '$entityClass' cannot be #[Encrypted]",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: 'Encrypted values cannot be looked up by value. Remove #[Encrypted] from the primary key',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function invalidTimestampProperty(
        string $entityClass,
        string $property,
        string $role,
    ): self {
        return new self(
            message: "#[Timestamps] $role property '$property' on entity '$entityClass' is not a DateTimeImmutable #[Column] property",
            context: "Parsing #[Timestamps] on entity '$entityClass'",
            suggestion: "Declare #[Column] public ?DateTimeImmutable \$$property, or pass the correct property name to #[Timestamps($role: ...)]",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function timestampsWithoutProperties(
        string $entityClass,
    ): self {
        return new self(
            message: "#[Timestamps] on entity '$entityClass' disables both createdAt and updatedAt",
            context: "Parsing #[Timestamps] on entity '$entityClass'",
            suggestion: 'Remove #[Timestamps], or keep at least one of createdAt/updatedAt',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function timestampsOnExtender(
        string $entityClass,
    ): self {
        return new self(
            message: "Extender entity '$entityClass' cannot declare #[Timestamps]",
            context: "Parsing #[Timestamps] on extender entity '$entityClass'",
            suggestion: 'Declare #[Timestamps] on the parent entity that owns the table',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptedUniqueOrIndexed(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Encrypted property '$property' on entity '$entityClass' is part of a unique column or an #[Index]",
            context: "Parsing column metadata for '$entityClass::\$$property'",
            suggestion: 'Ciphertext is non-deterministic, so unique constraints and indexes on it are meaningless. Remove unique/#[Index] or #[Encrypted]',
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function decryptionFailed(
        string $entityClass,
        string $property,
        string $column,
        Throwable $previous,
    ): self {
        return new self(
            message: "Failed to decrypt property '$property' (column '$column') on entity '$entityClass': {$previous->getMessage()}",
            context: "Hydrating '$entityClass::\$$property' from column '$column'",
            suggestion: 'Check that the encryption key matches the one used to write the data and that the column has not been modified',
            previous: $previous,
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function encryptedCriteria(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Cannot query by encrypted property '$property' on entity '$entityClass'",
            context: "Building findBy/findOneBy/existsBy criteria for '$entityClass'",
            suggestion: 'Encrypted values are non-deterministic and cannot be matched in SQL. Query by another column and compare after hydration',
        );
    }
}
