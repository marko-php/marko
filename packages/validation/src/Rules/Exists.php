<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RecordLookupInterface;
use Marko\Validation\Contracts\RuleInterface;

/**
 * The value must match a stored record of an entity, e.g. a foreign-key id from a form.
 *
 * Build it with RecordRules::exists(); there is no `exists:` rule string.
 */
readonly class Exists implements RuleInterface
{
    /**
     * @param class-string $entityClass
     * @param array<string, mixed> $where Property => value conditions the record must also match
     */
    public function __construct(
        private RecordLookupInterface $lookup,
        private string $entityClass,
        private string $property = 'id',
        private array $where = [],
    ) {}

    /**
     * Also require the record's $property to equal $value (null matches NULL).
     */
    public function where(
        string $property,
        mixed $value,
    ): self {
        return new self($this->lookup, $this->entityClass, $this->property, [...$this->where, $property => $value]);
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

        return $this->lookup->exists($this->entityClass, $this->property, $value, $this->where);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The selected $field is invalid.";
    }
}
