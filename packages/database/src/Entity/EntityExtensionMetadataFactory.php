<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use BackedEnum;
use Marko\Database\Attributes\BelongsTo;
use Marko\Database\Attributes\BelongsToMany;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\ExtensionOf;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\HasOne;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Exceptions\EntityException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Parses entity extension classes and extracts metadata from attributes.
 */
class EntityExtensionMetadataFactory
{
    /**
     * PHP type to database type mapping.
     */
    private const array TYPE_MAP = [
        'int' => 'integer',
        'string' => 'varchar',
        'float' => 'decimal',
        'bool' => 'boolean',
        'array' => 'json',
    ];

    /**
     * @var array<class-string, ExtensionMetadata>
     */
    private array $cache = [];

    /**
     * Parse an extension class and return its metadata.
     *
     * @param class-string $extensionClass
     *
     * @throws EntityException
     */
    public function parse(string $extensionClass): ExtensionMetadata
    {
        if (isset($this->cache[$extensionClass])) {
            return $this->cache[$extensionClass];
        }

        $reflection = new ReflectionClass($extensionClass);

        $this->validateExtension($reflection, $extensionClass);

        $entityClass = $this->extractEntityClass($reflection, $extensionClass);
        $columns = [];
        $properties = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $this->validateNoRelationship($property, $extensionClass);

            $columnAttributes = $property->getAttributes(Column::class);

            if (count($columnAttributes) === 0) {
                continue;
            }

            $columnAttr = $columnAttributes[0]->newInstance();
            $propertyName = $property->getName();

            if ($columnAttr->primaryKey) {
                throw EntityException::extensionDeclaresPrimaryKey($extensionClass, $propertyName);
            }

            $columnName = $columnAttr->name ?? $this->camelToSnakeCase($propertyName);
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw EntityException::missingTypeDeclaration($extensionClass, $propertyName);
            }

            $phpType = $type->getName();
            $dbType = $columnAttr->type ?? $this->inferDatabaseType($phpType);
            $nullable = $type->allowsNull();
            $default = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

            // Convert BackedEnum default values to their backing value for database storage
            if ($default instanceof BackedEnum) {
                $default = $default->value;
            }

            if ($columnAttr->type === 'json' && $phpType !== 'array') {
                throw EntityException::jsonColumnTypeMismatch($extensionClass, $propertyName, $phpType);
            }

            if ($columnAttr->type === 'json' && $columnAttr->nullable !== null && $columnAttr->nullable !== $nullable) {
                throw EntityException::jsonColumnNullableMismatch(
                    $extensionClass,
                    $propertyName,
                    $columnAttr->nullable,
                    $nullable,
                );
            }

            $columns[] = new ColumnMetadata(
                name: $columnName,
                type: $dbType,
                length: $columnAttr->length,
                nullable: $nullable,
                default: $columnAttr->default ?? $default,
                unique: $columnAttr->unique,
                primaryKey: false,
                autoIncrement: false,
                references: $columnAttr->references,
                onDelete: $columnAttr->onDelete,
                onUpdate: $columnAttr->onUpdate,
            );

            // Detect if the type is a BackedEnum
            $enumClass = null;
            if (enum_exists($phpType) && is_subclass_of($phpType, BackedEnum::class)) {
                $enumClass = $phpType;
            }

            $properties[$propertyName] = new PropertyMetadata(
                name: $propertyName,
                columnName: $columnName,
                type: $phpType,
                nullable: $nullable,
                isPrimaryKey: false,
                isAutoIncrement: false,
                enumClass: $enumClass,
                default: $columnAttr->default ?? $default,
                columnType: $columnAttr->type,
            );
        }

        if (count($columns) === 0) {
            throw EntityException::extensionHasNoColumns($extensionClass);
        }

        $metadata = new ExtensionMetadata(
            extensionClass: $extensionClass,
            entityClass: $entityClass,
            properties: $properties,
            columns: $columns,
        );

        $this->cache[$extensionClass] = $metadata;

        return $metadata;
    }

    /**
     * Clear the metadata cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Validate that the extension class is properly configured.
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string $extensionClass
     *
     * @throws EntityException
     */
    private function validateExtension(
        ReflectionClass $reflection,
        string $extensionClass,
    ): void {
        if (!$reflection->isSubclassOf(EntityExtension::class)) {
            throw EntityException::notExtendsEntityExtension($extensionClass);
        }

        if (count($reflection->getAttributes(Table::class)) > 0
            || count($reflection->getAttributes(Index::class)) > 0
        ) {
            throw EntityException::extensionDeclaresTableAttribute($extensionClass);
        }
    }

    /**
     * Extract the entity class from the #[ExtensionOf] attribute.
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string $extensionClass
     * @return class-string<Entity>
     *
     * @throws EntityException
     */
    private function extractEntityClass(
        ReflectionClass $reflection,
        string $extensionClass,
    ): string {
        $extensionOfAttrs = $reflection->getAttributes(ExtensionOf::class);

        if (count($extensionOfAttrs) === 0) {
            throw EntityException::extensionMissingExtensionOf($extensionClass);
        }

        return $extensionOfAttrs[0]->newInstance()->entityClass;
    }

    /**
     * Validate that the property does not declare a relationship attribute.
     *
     * @param class-string $extensionClass
     *
     * @throws EntityException
     */
    private function validateNoRelationship(
        ReflectionProperty $property,
        string $extensionClass,
    ): void {
        $hasRelationship = count($property->getAttributes(HasOne::class)) > 0
            || count($property->getAttributes(HasMany::class)) > 0
            || count($property->getAttributes(BelongsTo::class)) > 0
            || count($property->getAttributes(BelongsToMany::class)) > 0;

        if ($hasRelationship) {
            throw EntityException::extensionDeclaresRelationship($extensionClass, $property->getName());
        }
    }

    /**
     * Convert a camelCase property name to snake_case for use as a column name.
     */
    private function camelToSnakeCase(string $name): string
    {
        $result = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name);
        $result = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $result);

        return strtolower($result);
    }

    /**
     * Infer the database type from a PHP type.
     */
    private function inferDatabaseType(string $phpType): string
    {
        return self::TYPE_MAP[$phpType] ?? 'varchar';
    }
}
