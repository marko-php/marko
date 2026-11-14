<?php

declare(strict_types=1);

namespace Marko\Database\Config;

use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Marko\Core\Path\ProjectPaths;
use Marko\Database\Exceptions\ConfigurationException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * The timezone stored datetimes are written in, read from the optional `timezone`
 * key of config/database.php. Defaults to UTC when the key is absent.
 *
 * Entity datetimes follow it through DateTimeCast. Code that writes `Y-m-d H:i:s`
 * strings by hand (the queue tables, token expiry, notifications, webhook attempts)
 * follows it through format() and parse(), so a stored instant never depends on
 * the PHP default timezone of the process that wrote or reads it.
 *
 * Kept separate from DatabaseConfig because it applies to every driver, including
 * the read/write split driver whose config/database.php has no host settings.
 */
readonly class DatabaseTimezoneConfig
{
    public const string DEFAULT_TIMEZONE = 'UTC';

    public const string FORMAT = 'Y-m-d H:i:s';

    public DateTimeZone $timezone;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        ProjectPaths $paths,
    ) {
        $configPath = $paths->config . '/database.php';
        $config = file_exists($configPath) ? require $configPath : [];
        $name = is_array($config) ? ($config['timezone'] ?? self::DEFAULT_TIMEZONE) : self::DEFAULT_TIMEZONE;

        $this->timezone = self::resolveTimezone($name);
    }

    /**
     * Build the config for an explicit timezone name, without reading config files.
     *
     * @throws ConfigurationException|ReflectionException
     */
    public static function fromName(
        string $name,
    ): self {
        $instance = new ReflectionClass(self::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(self::class, 'timezone')->setValue($instance, self::resolveTimezone($name));

        return $instance;
    }

    /**
     * Convert an instant to the database timezone and format it for storage.
     */
    public function format(
        DateTimeInterface $instant,
    ): string {
        return DateTimeImmutable::createFromInterface($instant)
            ->setTimezone($this->timezone)
            ->format(self::FORMAT);
    }

    /**
     * Read a stored datetime string as a time in the database timezone.
     *
     * @throws DateMalformedStringException When the stored value is not a datetime
     */
    public function parse(
        string $value,
    ): DateTimeImmutable {
        return new DateTimeImmutable($value, $this->timezone);
    }

    /**
     * Validate a configured timezone name. Shared with DatabaseConfig, which reads the same key.
     *
     * @throws ConfigurationException
     */
    public static function resolveTimezone(
        mixed $name,
    ): DateTimeZone {
        if (
            !is_string($name)
            || $name === ''
        ) {
            throw ConfigurationException::invalidTimezone(var_export($name, true));
        }

        try {
            return new DateTimeZone($name);
        } catch (Exception) {
            throw ConfigurationException::invalidTimezone($name);
        }
    }
}
