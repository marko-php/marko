<?php

declare(strict_types=1);

namespace Marko\Api\Value;

readonly class ConditionalValue
{
    public function __construct(
        public bool $condition,
        public mixed $value,
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
