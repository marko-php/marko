<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

/**
 * A string that is valid JSON (any JSON value, not only objects and arrays).
 */
readonly class Json implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && json_validate($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be a valid JSON string.";
    }
}
