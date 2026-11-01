<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use InvalidArgumentException;

/**
 * Compares a value with the reference of an `after`/`before` style rule.
 *
 * The reference is another field when that field is present in the data (a dot path whose
 * `*` segments are filled with the current row's indexes), otherwise a date strtotime()
 * understands (`today`, `2026-01-01`). Both sides are read with DateValue, the same parsing
 * as the `date` rule.
 */
readonly class DateComparison
{
    /**
     * @param string $rule The rule name, for messages (e.g. `after`)
     *
     * @throws InvalidArgumentException When the reference is empty
     */
    public function __construct(
        private string $rule,
        public string $reference,
    ) {
        if (trim($reference) === '') {
            throw new InvalidArgumentException(
                "The $rule rule needs a date or a field, e.g. $rule:today or $rule:starts_at",
            );
        }
    }

    /**
     * Return the comparison with each `*` in the reference replaced, in order, by $indexes.
     *
     * @param list<string> $indexes
     *
     * @throws InvalidArgumentException When the reference has more wildcards than $indexes
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): self {
        return new self(
            $this->rule,
            new FieldReference($this->rule, $this->reference)->forWildcardIndexes($key, $indexes)->path,
        );
    }

    /**
     * Compare the value with the reference: -1 when it is earlier, 0 when equal, 1 when later,
     * or null when the value or the reference does not resolve to a date. A reference that is
     * neither a field present in the data nor a date is null too: the data is client input, so
     * a missing field is a validation failure, never an exception.
     *
     * @param array<mixed> $data
     */
    public function compare(
        mixed $value,
        array $data,
    ): ?int {
        $timestamp = DateValue::timestamp($value);
        $reference = $this->referenceTimestamp($data);

        if ($timestamp === null || $reference === null) {
            return null;
        }

        return $timestamp <=> $reference;
    }

    /**
     * @param array<mixed> $data
     */
    private function referenceTimestamp(
        array $data,
    ): ?int {
        if (DataPath::has($data, $this->reference)) {
            return DateValue::timestamp(DataPath::get($data, $this->reference));
        }

        return str_contains($this->reference, '*') ? null : DateValue::timestamp($this->reference);
    }
}
