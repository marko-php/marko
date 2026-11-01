<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

/**
 * The field is required when any of the other fields is filled (`required_with:email,phone`).
 */
readonly class RequiredWith extends AbstractRequiredWithRule
{
    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return in_array(true, $this->filled($data), true);
    }

    protected function name(): string
    {
        return 'required_with';
    }

    protected function relation(): string
    {
        return 'is present';
    }
}
