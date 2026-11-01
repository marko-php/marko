<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

readonly class Before extends AbstractDateComparisonRule
{
    protected function name(): string
    {
        return 'before';
    }

    protected function relation(): string
    {
        return 'before';
    }

    protected function accepts(
        int $comparison,
    ): bool {
        return $comparison < 0;
    }
}
