<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

readonly class Regex implements RuleInterface
{
    public function __construct(
        private string $pattern,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (!is_string($value) && !is_numeric($value)) {
            return false;
        }

        return preg_match($this->pattern, (string) $value) === 1;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field format is invalid.";
    }
}
