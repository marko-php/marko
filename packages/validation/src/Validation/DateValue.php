<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

use DateTimeInterface;

/**
 * Reads a validation value as a point in time.
 *
 * Shared by `date` and the date comparison rules so a value that passes `date` is always one
 * they can compare.
 */
class DateValue
{
    /**
     * The Unix timestamp of a DateTimeInterface or a string strtotime() understands, or null
     * for anything else.
     */
    public static function timestamp(
        mixed $value,
    ): ?int {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? null : $timestamp;
    }
}
