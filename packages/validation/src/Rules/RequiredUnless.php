<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

/**
 * The field is required unless another field holds one of the values
 * (`required_unless:status,approved`). An absent other field does not match, so the field
 * is required.
 */
readonly class RequiredUnless extends AbstractRequiredIfRule
{
    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return !$this->matches($data);
    }

    protected function name(): string
    {
        return 'required_unless';
    }

    protected function describe(
        string $otherField,
        string $values,
    ): string {
        return "unless $otherField is in $values";
    }
}
