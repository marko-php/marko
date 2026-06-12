<?php

declare(strict_types=1);

namespace Marko\Scheduler\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidCronExpressionException extends MarkoException
{
    public static function wrongFieldCount(
        string $expression,
        int $actual,
    ): self {
        return new self(
            message: "Cron expression '$expression' has $actual field(s) but exactly 5 are required.",
            context: "While parsing cron expression '$expression'",
            suggestion: 'A valid cron expression has five space-separated fields: minute hour day-of-month month day-of-week (e.g. "0 * * * *").',
        );
    }

    public static function unparseableField(
        string $field,
        string $expression,
    ): self {
        return new self(
            message: "Cron expression field '$field' in '$expression' cannot be parsed.",
            context: "While parsing cron expression '$expression'",
            suggestion: 'Each field must be *, a number, a range (a-b), a step (*/n or a-b/n), or a comma-separated list of those forms.',
        );
    }
}
