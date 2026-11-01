<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Validation\EmptyValue;

/**
 * The field must be absent or empty (null, a blank string or an empty array).
 */
class Prohibited implements ImplicitRuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return EmptyValue::is($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field is prohibited.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return false;
    }
}
