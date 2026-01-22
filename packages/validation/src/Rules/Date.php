<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use DateTimeInterface;
use Marko\Validation\Contracts\RuleInterface;

class Date implements RuleInterface
{
    public function __construct(
        private readonly ?string $format = null,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        if ($this->format !== null) {
            $date = date_create_from_format($this->format, $value);

            return $date !== false && $date->format($this->format) === $value;
        }

        return strtotime($value) !== false;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if ($this->format !== null) {
            return "The $field field must be a valid date in the format $this->format.";
        }

        return "The $field field must be a valid date.";
    }
}
