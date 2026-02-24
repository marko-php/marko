<?php

declare(strict_types=1);

namespace Marko\Api\Value;

class ConditionalValue
{
    public function __construct(
        public readonly bool $condition,
        public readonly mixed $value,
    ) {}

    /**
     * Resolve the value: returns the wrapped value if condition is true,
     * or a MissingValue sentinel if condition is false.
     */
    public function resolve(): mixed
    {
        if ($this->condition) {
            return $this->value;
        }

        return new MissingValue();
    }
}
