<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigException;
use Marko\Core\Environment\EnvironmentVariables;

/**
 * Typed reader for environment variables in config files.
 *
 * Config files run inside ConfigLoader before the container exists, so the
 * methods are static and need no DI: import the class and call it.
 *
 * Every method reads $_ENV first, then getenv(). An unset variable or an
 * empty string (KEY= in .env) returns the default. Any other value must
 * parse as the requested type, or a ConfigException naming the variable,
 * the value and the accepted forms is thrown, so a typo stops the boot
 * instead of silently becoming 0, false or true.
 */
class Env
{
    /** @var list<string> */
    public const array TRUE_VALUES = ['true', '1', 'yes', 'on'];

    /** @var list<string> */
    public const array FALSE_VALUES = ['false', '0', 'no', 'off'];

    private const string ACCEPTED_BOOLS = 'true, false, 1, 0, yes, no, on, off';

    /**
     * Start recording the name of every variable read through Env, or through
     * the marko/core readers (AppEnvironment, DiscoveryEnvironment) that the
     * shipped config/discovery.php uses. The recording lives in marko/core's
     * EnvironmentVariables, so both kinds of read land in one list.
     *
     * Used while compiling the config section of the discovery cache, so a
     * cached boot can detect that one of those variables changed since.
     *
     * @throws ConfigException When already recording
     */
    public static function startRecording(): void
    {
        if (EnvironmentVariables::isRecording()) {
            throw new ConfigException(
                message: 'Env is already recording environment variable reads',
                context: 'While starting to record environment variable reads',
                suggestion: 'Call Env::stopRecording() before starting a new recording.',
            );
        }

        EnvironmentVariables::startRecording();
    }

    /**
     * Stop recording and return the names read since startRecording(), in first-read order.
     *
     * @return list<string>
     * @throws ConfigException When not recording
     */
    public static function stopRecording(): array
    {
        if (!EnvironmentVariables::isRecording()) {
            throw new ConfigException(
                message: 'Env is not recording environment variable reads',
                context: 'While stopping a recording of environment variable reads',
                suggestion: 'Call Env::startRecording() first.',
            );
        }

        return EnvironmentVariables::stopRecording();
    }

    public static function isRecording(): bool
    {
        return EnvironmentVariables::isRecording();
    }

    /**
     * A hash of each variable's current raw value, keyed by name. An empty
     * variable hashes differently from an unset one: Env reads both as unset,
     * but the core readers do not (an empty DISCOVERY_CACHE_ENABLED disables
     * the cache, an unset one enables it). Reading values here is not recorded.
     *
     * @param list<string> $names
     * @return array<string, string>
     */
    public static function valueHashes(
        array $names,
    ): array {
        return EnvironmentVariables::valueHashes($names);
    }

    /**
     * @throws ConfigException
     */
    public static function string(
        string $name,
        string $default,
    ): string {
        return self::read($name) ?? $default;
    }

    /**
     * @throws ConfigException
     */
    public static function nullableString(
        string $name,
        ?string $default = null,
    ): ?string {
        return self::read($name) ?? $default;
    }

    /**
     * @throws ConfigException
     */
    public static function int(
        string $name,
        int $default,
        ?int $min = null,
        ?int $max = null,
    ): int {
        return self::nullableInt($name, $default, $min, $max) ?? $default;
    }

    /**
     * @throws ConfigException
     */
    public static function nullableInt(
        string $name,
        ?int $default = null,
        ?int $min = null,
        ?int $max = null,
    ): ?int {
        $raw = self::readTyped($name);

        if ($raw === null) {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        if ($value === false) {
            throw new ConfigException(
                message: sprintf('Environment variable "%s" must be an integer', $name),
                context: sprintf('Got "%s"', $raw),
                suggestion: sprintf(
                    'Set %s to a whole number written with digits only (no units, decimals or exponents)%s, or remove it to use the default (%s).',
                    $name,
                    self::rangeHint($min, $max),
                    self::describeDefault($default),
                ),
            );
        }

        self::assertInRange($name, $raw, $value, $min, $max);

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public static function float(
        string $name,
        float $default,
        ?float $min = null,
        ?float $max = null,
    ): float {
        $raw = self::readTyped($name);

        if ($raw === null) {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_FLOAT);

        if ($value === false) {
            throw new ConfigException(
                message: sprintf('Environment variable "%s" must be a number', $name),
                context: sprintf('Got "%s"', $raw),
                suggestion: sprintf(
                    'Set %s to a number such as 1.5 (use a dot as the decimal separator, no units)%s, or remove it to use the default (%s).',
                    $name,
                    self::rangeHint($min, $max),
                    self::describeDefault($default),
                ),
            );
        }

        self::assertInRange($name, $raw, $value, $min, $max);

        return $value;
    }

    /**
     * @throws ConfigException
     */
    public static function bool(
        string $name,
        bool $default,
    ): bool {
        $raw = self::readTyped($name);

        if ($raw === null) {
            return $default;
        }

        $token = strtolower(trim($raw));

        if (in_array($token, self::TRUE_VALUES, true)) {
            return true;
        }

        if (in_array($token, self::FALSE_VALUES, true)) {
            return false;
        }

        throw new ConfigException(
            message: sprintf('Environment variable "%s" must be a boolean', $name),
            context: sprintf('Got "%s"', $raw),
            suggestion: sprintf(
                'Set %s to one of: %s (case-insensitive), or remove it to use the default (%s).',
                $name,
                self::ACCEPTED_BOOLS,
                $default ? 'true' : 'false',
            ),
        );
    }

    /**
     * Split a comma-separated value into a list. Items are trimmed and empty items are dropped.
     *
     * @param list<string> $default
     * @return list<string>
     * @throws ConfigException
     */
    public static function list(
        string $name,
        array $default,
    ): array {
        $raw = self::readTyped($name);

        if ($raw === null) {
            return $default;
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $raw)),
            static fn (string $item): bool => $item !== '',
        ));
    }

    /**
     * The raw value for a typed reader, or null when it is unset, empty or only whitespace.
     *
     * @throws ConfigException
     */
    private static function readTyped(
        string $name,
    ): ?string {
        $value = self::read($name);

        return $value === null || trim($value) === '' ? null : $value;
    }

    /**
     * The raw value, or null when the variable is unset or an empty string.
     * Every reader goes through here, so this is where reads are recorded.
     *
     * @throws ConfigException
     */
    private static function read(
        string $name,
    ): ?string {
        EnvironmentVariables::record($name);

        return self::lookup($name);
    }

    /**
     * @throws ConfigException
     */
    private static function lookup(
        string $name,
    ): ?string {
        if (array_key_exists($name, $_ENV) && $_ENV[$name] !== null) {
            $value = self::stringify($name, $_ENV[$name]);
        } else {
            $fromGetenv = getenv($name);
            $value = $fromGetenv === false ? null : $fromGetenv;
        }

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * $_ENV normally holds strings, but code (and tests) may place other scalars in it.
     *
     * @throws ConfigException
     */
    private static function stringify(
        string $name,
        mixed $value,
    ): string {
        return match (true) {
            is_string($value) => $value,
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            default => throw new ConfigException(
                message: sprintf('Environment variable "%s" must be a string', $name),
                context: sprintf('$_ENV["%s"] holds %s', $name, get_debug_type($value)),
                suggestion: sprintf(
                    'Assign a string to $_ENV["%s"], the way .env files and the process environment provide it.',
                    $name,
                ),
            ),
        };
    }

    /**
     * @throws ConfigException
     */
    private static function assertInRange(
        string $name,
        string $raw,
        int|float $value,
        int|float|null $min,
        int|float|null $max,
    ): void {
        $belowMin = $min !== null && $value < $min;
        $aboveMax = $max !== null && $value > $max;

        if (!$belowMin && !$aboveMax) {
            return;
        }

        $message = match (true) {
            $min !== null && $max !== null => sprintf(
                'Environment variable "%s" must be between %s and %s',
                $name,
                $min,
                $max,
            ),
            $belowMin => sprintf('Environment variable "%s" must be at least %s', $name, $min),
            default => sprintf('Environment variable "%s" must be at most %s', $name, $max),
        };

        throw new ConfigException(
            message: $message,
            context: sprintf('Got "%s"', $raw),
            suggestion: sprintf('Set %s to a value%s.', $name, self::rangeHint($min, $max)),
        );
    }

    private static function rangeHint(
        int|float|null $min,
        int|float|null $max,
    ): string {
        return match (true) {
            $min !== null && $max !== null => sprintf(' from %s to %s', $min, $max),
            $min !== null => sprintf(' of %s or greater', $min),
            $max !== null => sprintf(' of %s or less', $max),
            default => '',
        };
    }

    private static function describeDefault(
        int|float|null $default,
    ): string {
        return $default === null ? 'unset' : (string) $default;
    }
}
