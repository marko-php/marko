<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class Confirmed implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        $confirmationField = $field . '_confirmation';
        $confirmationValue = $data[$confirmationField] ?? null;

        return $value === $confirmationValue;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field confirmation does not match.";
    }
}
