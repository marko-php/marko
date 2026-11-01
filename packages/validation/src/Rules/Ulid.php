<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

/**
 * A ULID: 26 Crockford base32 characters (no I, L, O or U), in either case, whose first
 * character is 0-7 so the 48-bit timestamp does not overflow.
 */
readonly class Ulid implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        return is_string($value) && preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/iD', $value) === 1;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must be a valid ULID.";
    }
}
