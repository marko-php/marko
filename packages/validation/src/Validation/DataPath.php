<?php

declare(strict_types=1);

namespace Marko\Validation\Validation;

/**
 * Reads a value from validation data by dot path (`user.address.city`, `items.0.sku`).
 *
 * Shared by the validator and the cross-field rules so every rule resolves a field the same way.
 */
class DataPath
{
    /**
     * The value at $path, or null when the key is absent. Use has() to tell an absent key
     * from one holding null.
     *
     * @param array<mixed> $data
     */
    public static function get(
        array $data,
        string $path,
    ): mixed {
        return self::resolve($data, $path)[1];
    }

    /**
     * Whether $path names a key present in the data, even one holding null.
     *
     * @param array<mixed> $data
     */
    public static function has(
        array $data,
        string $path,
    ): bool {
        return self::resolve($data, $path)[0];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{bool, mixed} Whether the key is present, and its value (null when absent)
     */
    private static function resolve(
        array $data,
        string $path,
    ): array {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }

            $value = $value[$segment];
        }

        return [true, $value];
    }

    /**
     * Replace each `*` segment of $path, in order, with the next of $indexes.
     *
     * Returns null when $path has more wildcards than there are indexes.
     *
     * @param list<string> $indexes
     */
    public static function fillWildcards(
        string $path,
        array $indexes,
    ): ?string {
        $segments = explode('.', $path);

        foreach ($segments as $position => $segment) {
            if ($segment !== '*') {
                continue;
            }

            if ($indexes === []) {
                return null;
            }

            $segments[$position] = array_shift($indexes);
        }

        return implode('.', $segments);
    }
}
