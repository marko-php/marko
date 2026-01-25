<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RuleInterface;

readonly class Same implements RuleInterface
{
    public function __construct(
        private string $otherField,
    ) {}

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if ($value === null || $value === '') {
            return true;
        }

        $otherValue = $data[$this->otherField] ?? null;

        return $value === $otherValue;
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return "The $field field must match the $this->otherField field.";
    }
}
