<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Validation\EmptyValue;

class Required implements ImplicitRuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return !EmptyValue::is($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field is required.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return true;
    }
}
