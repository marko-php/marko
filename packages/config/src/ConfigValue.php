<?php

declare(strict_types=1);

namespace Marko\Config;

use Marko\Config\Exceptions\ConfigException;

/**
 * Strict conversion of a stored config value to int or bool.
 *
 * Shared by ConfigRepository and test doubles so every implementation of
 * ConfigRepositoryInterface accepts the same values: an unrecognised string
 * throws instead of being cast to 0, false or true.
 */
class ConfigValue
{
    /**
     * Accepts an int, or a string that is a whole number ("8080", "-5").
     *
     * @throws ConfigException
     */
    public static function toInt(
        string $key,
        mixed $value,
    ): int {
        if (is_int($value)) {
            return $value;
        }

        $parsed = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT) : false;

        if ($parsed === false) {
            throw new ConfigException(
                message: sprintf('Configuration key "%s" is not an integer', $key),
                context: sprintf('Expected integer, got %s', self::describe($value)),
                suggestion: 'Ensure your config file returns an integer for this key. Read environment variables with Marko\\Config\\Env::int() so invalid values fail at boot.',
            );
        }

        return $parsed;
    }

    /**
     * Accepts a bool, the ints 0 and 1, or the Env::bool() tokens (case-insensitive).
     *
     * @throws ConfigException
     */
    public static function toBool(
        string $key,
        mixed $value,
    ): bool {
        if (is_bool($value)) {
            return $value;
        }

        $token = (is_string($value) || $value === 0 || $value === 1)
            ? strtolower(trim((string) $value))
            : null;

        if (
            $token !== null
            && in_array($token, Env::TRUE_VALUES, true)
        ) {
            return true;
        }

        if (
            $token !== null
            && in_array($token, Env::FALSE_VALUES, true)
        ) {
            return false;
        }

        throw new ConfigException(
            message: sprintf('Configuration key "%s" is not a boolean', $key),
            context: sprintf('Expected boolean, got %s', self::describe($value)),
            suggestion: 'Ensure your config file returns true or false for this key. Strings are accepted only as true, false, 1, 0, yes, no, on or off. Read environment variables with Marko\\Config\\Env::bool() so invalid values fail at boot.',
        );
    }

    /**
     * The type of a value, with the value itself for scalars (e.g. string '1.5').
     */
    private static function describe(
        mixed $value,
    ): string {
        return is_scalar($value)
            ? sprintf('%s %s', get_debug_type($value), var_export($value, true))
            : get_debug_type($value);
    }
}
