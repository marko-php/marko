<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

readonly class Max implements RuleInterface
{
    public function __construct(
        private int|float $maximum,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return count($value) <= $this->maximum;
        }

        if (is_numeric($value)) {
            return (float) $value <= $this->maximum;
        }

        if (is_string($value)) {
            return mb_strlen($value) <= $this->maximum;
        }

        return false;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if (is_array($value)) {
            return "The $field field must not have more than $this->maximum items.";
        }

        if (is_numeric($value)) {
            return "The $field field must not exceed $this->maximum.";
        }

        if (is_string($value)) {
            return "The $field field must not exceed $this->maximum characters.";
        }

        return "The $field field must not exceed $this->maximum.";
    }
}
