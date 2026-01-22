<?php

declare(strict_types=1);

namespace Marko\Validation\Contracts;

interface RuleInterface
{
    /**
     * Validate the given value.
     *
     * @param array<string, mixed> $data All data being validated (for cross-field validation)
     */
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool;

    /**
     * Get the validation error message.
     */
    public function message(
        string $field,
        mixed $value,
    ): string;
}
