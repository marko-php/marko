<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Contracts\RuleInterface;

/**
 * No stored record of an entity may already have the value, e.g. an email or a slug.
 *
 * This is a usability check, not a guarantee: two requests can both pass before either saves. Keep the unique
 * index on the column, and handle UniqueConstraintViolationException. Build it with RecordRules::unique(); there
 * is no `unique:` rule string.
 */
readonly class Unique implements RuleInterface
{
    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $where Property => value conditions that scope the check
     * @param mixed $ignoreId Primary key of the record to leave out, or null for none
     */
    public function __construct(
        private RecordLookupInterface $lookup,
        private string $entityClass,
        private string $property,
        private array $where = [],
        private mixed $ignoreId = null,
        private bool $caseInsensitive = false,
    ) {}

    /**
     * Only records whose $property equals $value count (null matches NULL), e.g. a slug unique per tenant.
     */
    public function where(
        string $property,
        mixed $value,
    ): self {
        return new self(
            $this->lookup,
            $this->entityClass,
            $this->property,
            [...$this->where, $property => $value],
            $this->ignoreId,
            $this->caseInsensitive,
        );
    }

    /**
     * Leave out the record with this primary key, e.g. the user being updated keeps their own email.
     */
    public function ignore(
        mixed $id,
    ): self {
        return new self(
            $this->lookup,
            $this->entityClass,
            $this->property,
            $this->where,
            $id,
            $this->caseInsensitive,
        );
    }

    /**
     * Compare with LOWER() on both sides, so `Ada@example.com` is taken by `ada@example.com` on every database,
     * whatever the column's collation.
     */
    public function caseInsensitive(): self
    {
        return new self(
            $this->lookup,
            $this->entityClass,
            $this->property,
            $this->where,
            $this->ignoreId,
            true,
        );
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return !$this->lookup->isTaken(
            $this->entityClass,
            $this->property,
            $value,
            $this->ignoreId,
            $this->where,
            $this->caseInsensitive,
        );
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field has already been taken.";
    }
}
