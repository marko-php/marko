<?php

declare(strict_types=1);

namespace Marko\Database\Entity\Cast;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Marko\Database\Config\DatabaseTimezoneConfig;
use Marko\Database\Entity\PropertyMetadata;

/**
 * Built-in cast for DateTimeImmutable properties.
 *
 * Values are converted to the database timezone (config `database.timezone`,
 * default UTC) before formatting, and read back in that timezone, so the stored
 * instant never depends on the PHP default timezone.
 */
readonly class DateTimeCast implements EquatableCastInterface
{
    private const string FORMAT = 'Y-m-d H:i:s';

    private DateTimeZone $timezone;

    public function __construct(
        ?DatabaseTimezoneConfig $timezoneConfig = null,
    ) {
        $this->timezone = $timezoneConfig->timezone
            ?? new DateTimeZone(DatabaseTimezoneConfig::DEFAULT_TIMEZONE);
    }

    /**
     * @throws DateMalformedStringException
     */
    public function toPhp(
        mixed $value,
        PropertyMetadata $meta,
    ): DateTimeImmutable {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone($this->timezone);
        }

        return new DateTimeImmutable((string) $value, $this->timezone);
    }

    public function toDatabase(
        mixed $value,
        PropertyMetadata $meta,
    ): mixed {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        return DateTimeImmutable::createFromInterface($value)
            ->setTimezone($this->timezone)
            ->format(self::FORMAT);
    }

    /**
     * Two datetimes are equal when they describe the same second, whatever their timezone.
     */
    public function equals(
        mixed $a,
        mixed $b,
        PropertyMetadata $meta,
    ): bool {
        if (
            $a instanceof DateTimeInterface
            && $b instanceof DateTimeInterface
        ) {
            return $a->getTimestamp() === $b->getTimestamp();
        }

        return $a === $b;
    }
}
