<?php

declare(strict_types=1);

namespace Marko\Scheduler;

use DateTimeInterface;

class CronExpression
{
    public static function matches(
        string $expression,
        DateTimeInterface $time,
    ): bool {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) {
            return false;
        }

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        return self::matchField($minute, (int) $time->format('i'))
            && self::matchField($hour, (int) $time->format('G'))
            && self::matchField($dayOfMonth, (int) $time->format('j'))
            && self::matchField($month, (int) $time->format('n'))
            && self::matchField($dayOfWeek, (int) $time->format('w'));
    }

    private static function matchField(
        string $field,
        int $value,
    ): bool {
        if ($field === '*') {
            return true;
        }

        // Handle step values: */5
        if (str_starts_with($field, '*/')) {
            $step = (int) substr($field, 2);

            return $step > 0 && $value % $step === 0;
        }

        // Handle comma-separated values: 1,15,30
        if (str_contains($field, ',')) {
            $values = array_map('intval', explode(',', $field));

            return in_array($value, $values, true);
        }

        // Handle ranges: 1-5
        if (str_contains($field, '-')) {
            [$min, $max] = array_map('intval', explode('-', $field));

            return $value >= $min && $value <= $max;
        }

        // Direct value
        return (int) $field === $value;
    }
}
