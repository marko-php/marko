<?php

declare(strict_types=1);

namespace Marko\Config;

readonly class ConfigMerger
{
    /**
     * Merge $override over $base. Associative arrays merge recursively; any
     * other value, null included, replaces the base value. A RemoveKey value
     * removes the key, and no RemoveKey marker is left in the result.
     */
    public function merge(
        array $base,
        array $override,
    ): array {
        $result = $base;

        foreach ($override as $key => $value) {
            if ($value instanceof RemoveKey) {
                unset($result[$key]);
            } elseif (
                is_array($value)
                && array_key_exists($key, $result)
                && is_array($result[$key])
                && $this->isAssociative($value)
                && $this->isAssociative($result[$key])
            ) {
                $result[$key] = $this->merge($result[$key], $value);
            } else {
                $result[$key] = is_array($value) ? $this->withoutRemovals($value) : $value;
            }
        }

        return $result;
    }

    public function mergeAll(
        array ...$configs,
    ): array {
        $result = [];

        foreach ($configs as $config) {
            $result = $this->merge($result, $config);
        }

        return $result;
    }

    /**
     * Drop RemoveKey markers from an array that has nothing beneath it to
     * remove keys from. A list stays a list.
     */
    private function withoutRemovals(
        array $array,
    ): array {
        $isList = array_is_list($array);
        $result = [];

        foreach ($array as $key => $value) {
            if ($value instanceof RemoveKey) {
                continue;
            }

            $result[$key] = is_array($value) ? $this->withoutRemovals($value) : $value;
        }

        return $isList ? array_values($result) : $result;
    }

    private function isAssociative(
        array $array,
    ): bool {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
