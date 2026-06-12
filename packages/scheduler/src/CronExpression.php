<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use DateTimeInterface;
use Marko\Scheduler\Exceptions\InvalidCronExpressionException;

class CronExpression
{
    /**
     * @throws InvalidCronExpressionException
     */
    public static function matches(
        string $expression,
        DateTimeInterface $time,
    ): bool {
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            throw InvalidCronExpressionException::wrongFieldCount($expression, count($parts));
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        // Validate all fields before parsing
        foreach ($parts as $field) {
            self::assertFieldValid($field, $expression);
        }

        // Normalise DOW field: alias 7 → 0 (both represent Sunday)
        $dayOfWeek = str_replace('7', '0', $dayOfWeek);

        if (!self::matchField($minute, (int) $time->format('i'))) {
            return false;
        }

        if (!self::matchField($hour, (int) $time->format('G'))) {
            return false;
        }

        if (!self::matchField($month, (int) $time->format('n'))) {
            return false;
        }

        // Standard cron OR semantics: when BOTH DOM and DOW are restricted (not *)
        // the day matches if EITHER matches; when one is *, only the other is checked.
        $domRestricted = $dayOfMonth !== '*';
        $dowRestricted = $dayOfWeek !== '*';

        if ($domRestricted && $dowRestricted) {
            return self::matchField($dayOfMonth, (int) $time->format('j'))
                || self::matchField($dayOfWeek, (int) $time->format('w'));
        }

        return self::matchField($dayOfMonth, (int) $time->format('j'))
            && self::matchField($dayOfWeek, (int) $time->format('w'));
    }

    /**
     * @throws InvalidCronExpressionException
     */
    private static function assertFieldValid(
        string $field,
        string $expression,
    ): void {
        if (!preg_match('/^[\d,\-\/\*]+$/', $field)) {
            throw InvalidCronExpressionException::unparseableField($field, $expression);
        }
    }

    private static function matchField(
        string $field,
        int $value,
    ): bool {
        if ($field === '*') {
            return true;
        }

        // Handle comma-separated list: each part may itself be a range or step
        if (str_contains($field, ',')) {
            return array_any(
                explode(',', $field),
                fn (string $part) => self::matchField($part, $value),
            );
        }

        // Handle step ranges: a-b/n or */n
        if (str_contains($field, '/')) {
            [$range, $stepStr] = explode('/', $field, 2);
            $step = (int) $stepStr;

            if ($step <= 0) {
                return false;
            }

            if ($range === '*') {
                return $value % $step === 0;
            }

            // a-b/n: generate values starting from a, stepping by n up to b
            [$min, $max] = array_map('intval', explode('-', $range, 2));

            for ($v = $min; $v <= $max; $v += $step) {
                if ($v === $value) {
                    return true;
                }
            }

            return false;
        }

        // Handle plain ranges: 1-5
        if (str_contains($field, '-')) {
            [$min, $max] = array_map('intval', explode('-', $field, 2));

            return $value >= $min && $value <= $max;
        }

        // Direct value
        return (int) $field === $value;
    }
}
