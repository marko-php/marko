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

    public function equals(
        self $other,
    ): bool {
        return $this->name === $other->name
            && $this->type === $other->type
            && $this->length === $other->length
            && $this->nullable === $other->nullable
            && $this->default === $other->default
            && $this->unique === $other->unique
            && $this->primaryKey === $other->primaryKey
            && $this->autoIncrement === $other->autoIncrement
            && $this->references === $other->references
            && $this->onDelete === $other->onDelete
            && $this->onUpdate === $other->onUpdate;
    }
}
