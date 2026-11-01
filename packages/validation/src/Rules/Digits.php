<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;

/**
 * Exactly the given number of digits (leading zeros count: `0123` has 4).
 */
readonly class Digits extends AbstractDigitsRule
{
    /**
     * @throws InvalidArgumentException When the length is below 1
     */
    public function __construct(
        private int $length,
    ) {
        if ($length < 1) {
            throw new InvalidArgumentException(
                "The digits rule needs a number of digits of at least 1, $length given",
            );
        }
    }

    protected function acceptsLength(
        int $length,
    ): bool {
        return $length === $this->length;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be $this->length digits.";
    }
}
