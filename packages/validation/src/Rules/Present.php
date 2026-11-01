<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Validation\DataPath;

/**
 * The field's key must exist in the data; its value may be empty or null.
 */
class Present implements ImplicitRuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return DataPath::has($data, $field);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be present.";
    }

    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return false;
    }
}
