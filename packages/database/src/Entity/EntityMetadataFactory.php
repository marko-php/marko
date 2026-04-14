<?php

declare(strict_types=1);

namespace Marko\Database\Entity;

use BackedEnum;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Exceptions\EntityException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Parses entity classes and extracts metadata from attributes.
 */
class EntityMetadataFactory
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
     * @var array<class-string, EntityMetadata>
     */
    private array $cache = [];

    /**
     * Parse an entity class and return its metadata.
     *
     * @param class-string $entityClass
     *
     * @throws EntityException
     */
    public function parse(
        string $entityClass,
    ): EntityMetadata {
        if (isset($this->cache[$entityClass])) {
            return $this->cache[$entityClass];
        }

        $reflection = new ReflectionClass($entityClass);

        $this->validateEntity($reflection, $entityClass);

        $tableName = $this->extractTableName($reflection);
        $columns = [];
        $indexes = [];
        $properties = [];
        $primaryKey = 'id';

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $columnAttributes = $property->getAttributes(Column::class);

            if (count($columnAttributes) === 0) {
                continue;
            }

            $columnAttr = $columnAttributes[0]->newInstance();
            $propertyName = $property->getName();
            $columnName = $columnAttr->name ?? $this->camelToSnakeCase($propertyName);
            $type = $property->getType();

            if (!$type instanceof ReflectionNamedType) {
                throw EntityException::missingTypeDeclaration($entityClass, $propertyName);
            }

            $phpType = $type->getName();
            $dbType = $columnAttr->type ?? $this->inferDatabaseType($phpType);
            $nullable = $type->allowsNull();
            $default = $property->hasDefaultValue() ? $property->getDefaultValue() : null;

            // Convert BackedEnum default values to their backing value for database storage
            if ($default instanceof BackedEnum) {
                $default = $default->value;
            }

            if ($columnAttr->autoIncrement && !$columnAttr->primaryKey) {
                throw EntityException::autoIncrementWithoutPrimaryKey($entityClass, $propertyName);
            }

            if ($columnAttr->primaryKey) {
                $primaryKey = $propertyName;
            }

            $columns[] = new ColumnMetadata(
                name: $columnName,
                type: $dbType,
                length: $columnAttr->length,
                nullable: $nullable,
                default: $columnAttr->default ?? $default,
                unique: $columnAttr->unique,
                primaryKey: $columnAttr->primaryKey,
                autoIncrement: $columnAttr->autoIncrement,
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
                isPrimaryKey: $columnAttr->primaryKey,
                isAutoIncrement: $columnAttr->autoIncrement,
                enumClass: $enumClass,
                default: $columnAttr->default ?? $default,
            );
        }

        if (count($columns) === 0) {
            throw EntityException::noColumns($entityClass);
        }

        $indexAttributes = $reflection->getAttributes(Index::class);
        foreach ($indexAttributes as $indexAttr) {
            $index = $indexAttr->newInstance();
            $indexes[] = new IndexMetadata(
                name: $index->name,
                columns: $index->columns,
                unique: $index->unique,
            );
        }

        $metadata = new EntityMetadata(
            entityClass: $entityClass,
            tableName: $tableName,
            primaryKey: $primaryKey,
            properties: $properties,
            columns: $columns,
            indexes: $indexes,
        );

        $this->cache[$entityClass] = $metadata;

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
     * Validate that the entity class is properly configured.
     *
     * @param ReflectionClass<object> $reflection
     * @param class-string $entityClass
     *
     * @throws EntityException
     */
    private function validateEntity(
        ReflectionClass $reflection,
        string $entityClass,
    ): void {
        if (!$reflection->isSubclassOf(Entity::class)) {
            throw EntityException::notExtendsEntity($entityClass);
        }

        $tableAttributes = $reflection->getAttributes(Table::class);
        if (count($tableAttributes) === 0) {
            throw EntityException::missingTableAttribute($entityClass);
        }
    }

    /**
     * Extract the table name from the #[Table] attribute.
     *
     * @param ReflectionClass<object> $reflection
     */
    private function extractTableName(
        ReflectionClass $reflection,
    ): string {
        $tableAttributes = $reflection->getAttributes(Table::class);

        return $tableAttributes[0]->newInstance()->name;
    }

    /**
     * Convert a camelCase property name to snake_case for use as a column name.
     */
    private function camelToSnakeCase(
        string $name,
    ): string {
        $result = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $name);
        $result = (string) preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $result);

        return strtolower($result);
    }

    /**
     * Infer the database type from a PHP type.
     */
    private function inferDatabaseType(
        string $phpType,
    ): string {
        return self::TYPE_MAP[$phpType] ?? 'varchar';
    }
}
