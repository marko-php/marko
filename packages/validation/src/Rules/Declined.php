<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\ImplicitRuleInterface;

/**
 * The field must be `no`, `off`, `0`, `false`, `"0"` or `"false"`. An absent key fails.
 */
class Declined implements ImplicitRuleInterface
{
    public const array VALUES = ['no', 'off', '0', 0, false, 'false'];

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return in_array($value, self::VALUES, true);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be declined.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return false;
    }
}
