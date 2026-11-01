<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

/**
 * Decides whether a value counts as empty for validation: null, a blank string or an empty
 * array. `0`, `'0'` and `false` are values.
 *
 * Shared by the validator and the presence rules so every rule agrees on what "empty" means.
 */
class EmptyValue
{
    public static function is(
        mixed $value,
    ): bool {
        return $value === null
            || $value === []
            || (is_string($value) && trim($value) === '');
    }
}
