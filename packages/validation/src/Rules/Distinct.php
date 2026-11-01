<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;

/**
 * No other value matched by the same wildcard key equals this one (`items.*.sku`).
 *
 * By default numeric values compare by value (`1`, `"1"` and `"01"` are duplicates) and other
 * scalars by their string form. `strict` compares with `===`; `ignore_case` lowercases strings
 * first. Absent and null siblings are ignored.
 */
readonly class Distinct implements WildcardAwareRuleInterface
{
    /**
     * @param ?string $key The wildcard rules key whose values are compared; the validator sets
     *                     it through forWildcardIndexes()
     */
    public function __construct(
        private bool $strict = false,
        private bool $ignoreCase = false,
        private ?string $key = null,
    ) {}

    /**
     * @throws InvalidArgumentException When the rule has not been bound to a wildcard key
     */
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($this->key === null) {
            throw new InvalidArgumentException(
                "The distinct rule has no wildcard key to compare $field against: validate it under a key such as items.*.sku",
            );
        }

        if ($value === null || $value === '') {
            return true;
        }

        foreach ($this->values(explode('.', $this->key), $data, '') as $path => $other) {
            if ((string) $path !== $field && $other !== null && $this->same($value, $other)) {
                return false;
            }
        }

        return true;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field has a duplicate value.";
    }

    /**
     * @throws InvalidArgumentException When the key has no wildcard
     */
    public function forWildcardIndexes(
        string $key,
        array $indexes,
    ): RuleInterface {
        if (!in_array('*', explode('.', $key), true)) {
            throw new InvalidArgumentException(
                "The distinct rule compares the items of an array, so its key needs a wildcard such as items.*.sku; $key has none",
            );
        }

        return new self($this->strict, $this->ignoreCase, $key);
    }

    /**
     * Every value the key's segments reach in the data, keyed by its concrete path.
     *
     * @param list<string> $segments
     *
     * @return array<string, mixed>
     */
    private function values(
        array $segments,
        mixed $value,
        string $prefix,
    ): array {
        if ($segments === []) {
            return [$prefix => $value];
        }

        if (!is_array($value)) {
            return [];
        }

        $segment = array_shift($segments);
        $keys = $segment === '*' ? array_keys($value) : [$segment];
        $values = [];

        foreach ($keys as $index) {
            $index = (string) $index;

            if (array_key_exists($index, $value)) {
                // `+` keeps the path keys: a spread would renumber a top-level path such as `0`.
                $values += $this->values($segments, $value[$index], $prefix === '' ? $index : "$prefix.$index");
            }
        }

        return $values;
    }

    private function same(
        mixed $value,
        mixed $other,
    ): bool {
        if ($this->ignoreCase) {
            $value = is_string($value) ? mb_strtolower($value) : $value;
            $other = is_string($other) ? mb_strtolower($other) : $other;
        }

        if ($this->strict) {
            return $value === $other;
        }

        if (is_numeric($value) && is_numeric($other)) {
            return (float) $value === (float) $other;
        }

        if (is_scalar($value) && is_scalar($other)) {
            return (string) $value === (string) $other;
        }

        return $value === $other;
    }
}
