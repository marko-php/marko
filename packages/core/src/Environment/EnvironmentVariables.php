<?php

declare(strict_types=1);

namespace Marko\Core\Environment;

use Marko\Core\Exceptions\EnvironmentException;

/**
 * The single raw reader for environment variables, and the recorder of which
 * ones were read.
 *
 * Reads $_ENV with a getenv() fallback, so it works whether or not marko/env
 * is installed and regardless of PHP's variables_order setting. AppEnvironment,
 * DiscoveryEnvironment and marko/config's Env all read through here (Env
 * records its reads with record()), so one recording sees every variable a
 * config file read, whichever reader it used. The discovery cache uses that
 * to detect, on a cached boot, that a variable changed since the cache was built.
 *
 * The methods are static because the readers are created inside config files,
 * before the container exists.
 */
class EnvironmentVariables
{
    /** @var array<string, true>|null Names read since startRecording(), or null when not recording */
    private static ?array $recordedNames = null;

    /**
     * The raw value, or null when the variable is unset. Recorded while recording.
     */
    public static function read(
        string $name,
    ): ?string {
        self::record($name);

        return self::lookup($name);
    }

    /**
     * Record a variable read by a reader that looks values up itself.
     */
    public static function record(
        string $name,
    ): void {
        if (self::$recordedNames !== null) {
            self::$recordedNames[$name] = true;
        }
    }

    /**
     * @throws EnvironmentException When already recording
     */
    public static function startRecording(): void
    {
        if (self::$recordedNames !== null) {
            throw EnvironmentException::alreadyRecording();
        }

        self::$recordedNames = [];
    }

    /**
     * Stop recording and return the names read since startRecording(), in first-read order.
     *
     * @return list<string>
     * @throws EnvironmentException When not recording
     */
    public static function stopRecording(): array
    {
        if (self::$recordedNames === null) {
            throw EnvironmentException::notRecording();
        }

        $names = array_map(strval(...), array_keys(self::$recordedNames));
        self::$recordedNames = null;

        return $names;
    }

    public static function isRecording(): bool
    {
        return self::$recordedNames !== null;
    }

    /**
     * A hash of each variable's current raw value, keyed by name, so the value
     * itself is never stored. An empty variable hashes differently from an
     * unset one, because some readers treat them differently (an empty
     * DISCOVERY_CACHE_ENABLED disables the cache, an unset one enables it).
     * Reading values here is not recorded.
     *
     * @param list<string> $names
     * @return array<string, string>
     */
    public static function valueHashes(
        array $names,
    ): array {
        $hashes = [];

        foreach ($names as $name) {
            $value = self::lookup($name);
            $hashes[$name] = hash('xxh128', $value === null ? 'unset' : 'set:' . $value);
        }

        return $hashes;
    }

    private static function lookup(
        string $name,
    ): ?string {
        if (array_key_exists($name, $_ENV) && is_scalar($_ENV[$name])) {
            return (string) $_ENV[$name];
        }

        $value = getenv($name);

        return $value === false ? null : $value;
    }
}
