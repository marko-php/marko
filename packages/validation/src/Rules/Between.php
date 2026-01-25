<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

readonly class Between implements RuleInterface
{
    public function __construct(
        private int|float $minimum,
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

        if (is_string($value)) {
            $length = mb_strlen($value);

            return $length >= $this->minimum && $length <= $this->maximum;
        }

        if (is_array($value)) {
            $count = count($value);

            return $count >= $this->minimum && $count <= $this->maximum;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;

            return $numeric >= $this->minimum && $numeric <= $this->maximum;
        }

        return false;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if (is_string($value)) {
            return "The $field field must be between $this->minimum and $this->maximum characters.";
        }

        if (is_array($value)) {
            return "The $field field must have between $this->minimum and $this->maximum items.";
        }

        return "The $field field must be between $this->minimum and $this->maximum.";
    }
}
