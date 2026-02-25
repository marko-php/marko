<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Column
{
    public function __construct(
        public string $name,
        public string $type,
        public ?int $length = null,
        public bool $nullable = false,
        public mixed $default = null,
        public bool $unique = false,
        public bool $primaryKey = false,
        public bool $autoIncrement = false,
        public ?string $references = null,
        public ?string $onDelete = null,
        public ?string $onUpdate = null,
    ) {}

    public function withPrimaryKey(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: $this->nullable,
            default: $this->default,
            unique: $this->unique,
            primaryKey: true,
            autoIncrement: $this->autoIncrement,
            references: $this->references,
            onDelete: $this->onDelete,
            onUpdate: $this->onUpdate,
        );
    }

    public function withAutoIncrement(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: $this->nullable,
            default: $this->default,
            unique: $this->unique,
            primaryKey: $this->primaryKey,
            autoIncrement: true,
            references: $this->references,
            onDelete: $this->onDelete,
            onUpdate: $this->onUpdate,
        );
    }

    public function withNullable(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: true,
            default: $this->default,
            unique: $this->unique,
            primaryKey: $this->primaryKey,
            autoIncrement: $this->autoIncrement,
            references: $this->references,
            onDelete: $this->onDelete,
            onUpdate: $this->onUpdate,
        );
    }

    public function withUnique(): self
    {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: $this->nullable,
            default: $this->default,
            unique: true,
            primaryKey: $this->primaryKey,
            autoIncrement: $this->autoIncrement,
            references: $this->references,
            onDelete: $this->onDelete,
            onUpdate: $this->onUpdate,
        );
    }

    public function withDefault(
        mixed $default,
    ): self {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: $this->nullable,
            default: $default,
            unique: $this->unique,
            primaryKey: $this->primaryKey,
            autoIncrement: $this->autoIncrement,
            references: $this->references,
            onDelete: $this->onDelete,
            onUpdate: $this->onUpdate,
        );
    }

    public function withReference(
        string $references,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        return new self(
            name: $this->name,
            type: $this->type,
            length: $this->length,
            nullable: $this->nullable,
            default: $this->default,
            unique: $this->unique,
            primaryKey: $this->primaryKey,
            autoIncrement: $this->autoIncrement,
            references: $references,
            onDelete: $onDelete,
            onUpdate: $onUpdate,
        );
    }

    /**
     * Compare two columns for equality.
     *
     * Note: This intentionally excludes references, onDelete, and onUpdate
     * because foreign key relationships are handled separately via ForeignKey
     * objects in the Table's foreignKeys array.
     */
    public function equals(
        self $other,
    ): bool {
        return $this->name === $other->name
            && $this->typeEquals($other)
            && $this->lengthEquals($other)
            && $this->nullableEquals($other)
            && $this->defaultEquals($other)
            && $this->unique === $other->unique
            && $this->primaryKey === $other->primaryKey
            && $this->autoIncrement === $other->autoIncrement;
    }

    /**
     * Types that are logically equivalent when stored in the database.
     * For example, 'enum' is stored as 'varchar' in PostgreSQL.
     */
    private const array TYPE_ALIASES = [
        'enum' => 'varchar',
        'datetime' => 'timestamp',
    ];

    /**
     * Compare types with case-insensitive matching and alias resolution.
     */
    private function typeEquals(
        self $other,
    ): bool {
        $thisType = self::TYPE_ALIASES[strtolower($this->type)] ?? strtolower($this->type);
        $otherType = self::TYPE_ALIASES[strtolower($other->type)] ?? strtolower($other->type);

        return $thisType === $otherType;
    }

    /**
     * Compare length with tolerance for database defaults.
     *
     * When entity doesn't specify length (null), the database will use defaults
     * (e.g., VARCHAR defaults to 255, TEXT has implicit length). These should
     * be considered equal when the entity didn't explicitly set a length.
     */
    private function lengthEquals(
        self $other,
    ): bool {
        // If both are null or both have same value, they're equal
        if ($this->length === $other->length) {
            return true;
        }

        // For TEXT type, length comparison is irrelevant (TEXT has no user-specified length)
        if (strtolower($this->type) === 'text') {
            return true;
        }

        // If entity doesn't specify length (null), accept database's default
        // This handles VARCHAR defaulting to 255, etc.
        if ($this->length === null && $other->length !== null) {
            return true;
        }

        return false;
    }

    /**
     * Compare nullable with consideration for primary keys.
     *
     * Primary keys are never nullable in the database, even if the PHP
     * property is nullable (e.g., ?int $id = null for auto-increment columns
     * that are set after insert).
     */
    private function nullableEquals(
        self $other,
    ): bool {
        // If same, they're equal
        if ($this->nullable === $other->nullable) {
            return true;
        }

        // Primary keys with auto-increment are never nullable in DB
        // even if entity declares them as nullable PHP type
        if ($this->primaryKey && $this->autoIncrement) {
            return true;
        }

        return false;
    }

    /**
     * Compare default values with tolerance for migration defaults.
     *
     * When entity doesn't specify a default (null) but the database has one,
     * this can happen because:
     * - Migration added a NOT NULL column with temporary default
     * - The application always provides values at runtime
     *
     * In these cases, we consider them equal if entity has no explicit default.
     */
    private function defaultEquals(
        self $other,
    ): bool {
        // If both are same, they're equal
        if ($this->default === $other->default) {
            return true;
        }

        // If entity doesn't specify default (null), accept database's default
        // This handles migration defaults for NOT NULL columns
        if ($this->default === null && $other->default !== null) {
            return true;
        }

        return false;
    }
}
