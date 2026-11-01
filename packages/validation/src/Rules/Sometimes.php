<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

/**
 * Meta rule: when the field's key is absent from the data, the validator skips all of the
 * field's rules. When the key is present, even holding null, every rule applies.
 */
class Sometimes implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return true;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return '';
    }
}
