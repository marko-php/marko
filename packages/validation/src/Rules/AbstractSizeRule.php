<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\NumericAwareRuleInterface;

/**
 * Base for rules that compare the size of a value: an int or float by value, an array by
 * item count, and a string by length --- or by value when the rule is in numeric mode
 * (the field also has a `numeric` or `integer` rule) and the string is numeric.
 */
abstract readonly class AbstractSizeRule implements NumericAwareRuleInterface
{
    public function __construct(
        protected bool $numeric = false,
    ) {}

    /**
     * The size to compare, or null when the value has no size these rules can measure.
     */
    protected function size(
        mixed $value,
    ): int|float|null {
        if (
            is_int($value)
            || is_float($value)
        ) {
            return $value;
        }

        if (is_array($value)) {
            return count($value);
        }

        if (is_string($value)) {
            return $this->measuresLength($value) ? mb_strlen($value) : (float) $value;
        }

        return null;
    }

    /**
     * Whether the value is a string measured by its length rather than by its value.
     */
    protected function measuresLength(
        mixed $value,
    ): bool {
        return is_string($value)
            && !($this->numeric
            && is_numeric($value));
    }
}
