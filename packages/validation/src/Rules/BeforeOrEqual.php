<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

readonly class BeforeOrEqual extends AbstractDateComparisonRule
{
    protected function name(): string
    {
        return 'before_or_equal';
    }

    protected function relation(): string
    {
        return 'before or equal to';
    }

    protected function accepts(
        int $comparison,
    ): bool {
        return $comparison <= 0;
    }
}
