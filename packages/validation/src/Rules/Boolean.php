<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class Boolean implements RuleInterface
{
    private const ACCEPTABLE_VALUES = [true, false, 0, 1, '0', '1'];

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null) {
            return true;
        }

        return in_array($value, self::ACCEPTABLE_VALUES, true);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be true or false.";
    }
}
