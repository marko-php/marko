<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Validation\DataPath;

class Confirmed implements RuleInterface
{
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if (
            $value === null
            || $value === ''
        ) {
            return true;
        }

        return $value === DataPath::get($data, $field . '_confirmation');
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field confirmation does not match.";
    }
}
