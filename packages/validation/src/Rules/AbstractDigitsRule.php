<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

/**
 * Base for rules that count the digits of a value: a string of the characters 0-9 only, or
 * a non-negative integer. Signs, decimal points and floats never pass.
 */
abstract readonly class AbstractDigitsRule implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_int($value) && $value >= 0) {
            $value = (string) $value;
        }

        return is_string($value)
            && preg_match('/^[0-9]+$/D', $value) === 1
            && $this->acceptsLength(strlen($value));
    }

    /**
     * Whether a value of this many digits passes.
     */
    abstract protected function acceptsLength(
        int $length,
    ): bool;
}
