<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

readonly class AfterOrEqual extends AbstractDateComparisonRule
{
    protected function name(): string
    {
        return 'after_or_equal';
    }

    protected function relation(): string
    {
        return 'after or equal to';
    }

    protected function accepts(
        int $comparison,
    ): bool {
        return $comparison >= 0;
    }
}
