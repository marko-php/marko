<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

readonly class After extends AbstractDateComparisonRule
{
    protected function name(): string
    {
        return 'after';
    }

    protected function relation(): string
    {
        return 'after';
    }

    protected function accepts(
        int $comparison,
    ): bool {
        return $comparison > 0;
    }
}
