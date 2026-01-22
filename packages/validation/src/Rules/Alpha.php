<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class Alpha implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return preg_match('/^[\pL\pM]+$/u', $value) === 1;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must only contain letters.";
    }
}
