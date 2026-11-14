<?php

declare(strict_types=1);

namespace Marko\Database\Schema;

readonly class Column
{
    /**
     * @param string|null $nativeType The driver-native type the database reported, such as
     *                                `decimal(12,4) unsigned`. Set by introspection, ignored by equals().
     * @param string|null $collation The collation the database reported, such as `utf8mb4_bin`. Set by
     *                               introspection, ignored by equals().
     * @param string|null $onUpdateExpression The expression the database writes on every row update, such as
     *                                        `CURRENT_TIMESTAMP`. Set by introspection, ignored by equals().
     *                                        Not the foreign key action, which is $onUpdate.
     */
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
        public ?string $nativeType = null,
        public ?string $collation = null,
        public ?string $onUpdateExpression = null,
    ) {}

    public function withPrimaryKey(): self
    {
        return clone($this, ['primaryKey' => true]);
    }

    public function withAutoIncrement(): self
    {
        return clone($this, ['autoIncrement' => true]);
    }

    public function withNullable(): self
    {
        return clone($this, ['nullable' => true]);
    }

    public function withUnique(): self
    {
        return clone($this, ['unique' => true]);
    }

    /**
     * The same column without the unique flag, for comparisons that leave uniqueness to the index diff.
     */
    public function withoutUnique(): self
    {
        return clone($this, ['unique' => false]);
    }

    public function withDefault(
        mixed $default,
    ): self {
        return clone($this, ['default' => $default]);
    }

    public function withReference(
        string $references,
        ?string $onDelete = null,
        ?string $onUpdate = null,
    ): self {
        return clone($this, [
            'references' => $references,
            'onDelete' => $onDelete,
            'onUpdate' => $onUpdate,
        ]);
    }

    /**
     * The column an up migration actually moves to when this (entity) column replaces $previous, the
     * database's current definition.
     *
     * It applies the tolerances equals() applies, so the generated SQL never changes what the diff
     * accepted: a column that declares no length or no default keeps the database's, and an
     * auto-increment primary key keeps the database's nullability whatever the PHP property allows.
     * The driver-native metadata an entity cannot declare (native type, collation, on-update
     * expression) is carried over from $previous; each SQL generator decides whether it still applies
     * to the resolved type.
     */
    public function resolveAgainst(
        self $previous,
    ): self {
        return clone($this, [
            'length' => $this->length ?? $previous->length,
            'nullable' => ($this->primaryKey && $this->autoIncrement)
                ? $previous->nullable
                : $this->nullable,
            'default' => $this->default ?? $previous->default,
            'nativeType' => $this->nativeType ?? $previous->nativeType,
            'collation' => $this->collation ?? $previous->collation,
            'onUpdateExpression' => $this->onUpdateExpression ?? $previous->onUpdateExpression,
        ]);
    }

    /**
     * Compare two columns for equality.
     *
     * Note: This intentionally excludes references, onDelete, and onUpdate
     * because foreign key relationships are handled separately via ForeignKey
     * objects in the Table's foreignKeys array. The driver-native metadata
     * (nativeType, collation, onUpdateExpression) is excluded too: an entity
     * cannot declare it, so it never makes a column differ.
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
     * Whether both columns have the same default, without the tolerance equals() applies to an entity that
     * declares none.
     *
     * A shortcut string (`'NOW()'`) is the same default as the matching Expression, expressions compare as
     * Expression::equals() does, and a Literal is the same default as the plain string it holds.
     */
    public function hasSameDefaultAs(
        self $other,
    ): bool {
        return self::canonicalDefault($this->default) === self::canonicalDefault($other->default);
    }

    /**
     * The default as the diff compares it: an expression (or shortcut string) becomes its normalized SQL,
     * tagged so it never equals a string literal with the same text, and a Literal becomes its string.
     */
    private static function canonicalDefault(
        mixed $default,
    ): mixed {
        if ($default instanceof Literal) {
            return $default->value;
        }

        if ($default instanceof Expression) {
            return ['expression' => Expression::normalize($default->sql)];
        }

        if (
            is_string($default)
            && Expression::isShortcut($default)
        ) {
            return ['expression' => Expression::normalize($default)];
        }

        return $default;
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
        if (
            $this->length === null
            && $other->length !== null
        ) {
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
        if (
            $this->primaryKey
            && $this->autoIncrement
        ) {
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
        if ($this->hasSameDefaultAs($other)) {
            return true;
        }

        // If entity doesn't specify default (null), accept database's default
        // This handles migration defaults for NOT NULL columns
        if (
            $this->default === null
            && $other->default !== null
        ) {
            return true;
        }

        return false;
    }
}
