<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\ImplicitRuleInterface;

/**
 * The field must be `yes`, `on`, `1`, `true`, `"1"` or `"true"`, e.g. a ticked terms
 * checkbox. An absent key fails, since an unticked checkbox is not sent.
 */
class Accepted implements ImplicitRuleInterface
{
    public const array VALUES = ['yes', 'on', '1', 1, true, 'true'];

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
        return "The $field field must be accepted.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return false;
    }
}
