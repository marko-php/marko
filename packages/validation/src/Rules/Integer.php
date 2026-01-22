<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class Integer implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_int($value)) {
            return true;
        }

        if (is_string($value) && ctype_digit(ltrim($value, '-'))) {
            return str_starts_with($value, '-')
                ? ctype_digit(substr($value, 1))
                : ctype_digit($value);
        }

        return false;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be an integer.";
    }
}
