<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

/**
 * The field is required when another field holds one of the values
 * (`required_if:type,video,audio`). Otherwise an empty field skips its other rules.
 */
readonly class RequiredIf extends AbstractRequiredIfRule
{
    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return $this->matches($data);
    }

    protected function name(): string
    {
        return 'required_if';
    }

    protected function describe(
        string $otherField,
        string $values,
    ): string {
        return "when $otherField is $values";
    }
}
