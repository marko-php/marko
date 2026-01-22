<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class StringType implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null) {
            return true;
        }

        return is_string($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be a string.";
    }
}
