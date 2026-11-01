<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

/**
 * The field is required when any of the other fields is empty or absent
 * (`required_without:email,phone`).
 */
readonly class RequiredWithout extends AbstractRequiredWithRule
{
    public function requiresValue(
        string $field,
        array $data,
    ): bool {
        return in_array(false, $this->filled($data), true);
    }

    protected function name(): string
    {
        return 'required_without';
    }

    protected function relation(): string
    {
        return 'is not present';
    }
}
