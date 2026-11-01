<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

/**
 * An array whose keys are 0, 1, 2, ... in order (`array_is_list`).
 */
readonly class ListType implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        return is_array($value) && array_is_list($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be a list.";
    }
}
