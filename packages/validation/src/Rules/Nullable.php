<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

class Nullable implements RuleInterface
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
