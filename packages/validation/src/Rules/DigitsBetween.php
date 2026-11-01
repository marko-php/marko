<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;

/**
 * A number of digits within the given range, inclusive.
 */
readonly class DigitsBetween extends AbstractDigitsRule
{
    /**
     * @throws InvalidArgumentException When the minimum is below 1 or above the maximum
     */
    public function __construct(
        private int $minimum,
        private int $maximum,
    ) {
        if ($minimum < 1 || $minimum > $maximum) {
            throw new InvalidArgumentException(
                "The digits_between rule needs a minimum of at least 1 and no greater than its maximum, $minimum,$maximum given",
            );
        }
    }

    protected function acceptsLength(
        int $length,
    ): bool {
        return $length >= $this->minimum && $length <= $this->maximum;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be between $this->minimum and $this->maximum digits.";
    }
}
