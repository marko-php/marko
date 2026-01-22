<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class ArrayType implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null) {
            return true;
        }

        return is_array($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be an array.";
    }
}
