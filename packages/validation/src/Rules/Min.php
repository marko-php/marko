<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

readonly class Min implements RuleInterface
{
    public function __construct(
        private int|float $minimum,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_string($value)) {
            return mb_strlen($value) >= $this->minimum;
        }

        if (is_array($value)) {
            return count($value) >= $this->minimum;
        }

        if (is_numeric($value)) {
            return (float) $value >= $this->minimum;
        }

        return false;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if (is_string($value)) {
            return "The $field field must be at least $this->minimum characters.";
        }

        if (is_array($value)) {
            return "The $field field must have at least $this->minimum items.";
        }

        return "The $field field must be at least $this->minimum.";
    }
}
