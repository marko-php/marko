<?php

declare(strict_types=1);

namespace Marko\Config;

readonly class ConfigMerger
{
    public function merge(
        array $base,
        array $override,
    ): array {
        $result = $base;

        foreach ($override as $key => $value) {
            if ($value === null) {
                unset($result[$key]);
            } elseif (
                is_array($value)
                && isset($result[$key])
                && is_array($result[$key])
                && $this->isAssociative($value)
                && $this->isAssociative($result[$key])
            ) {
                $result[$key] = $this->merge($result[$key], $value);
            } else {
                $result[$key] = $value;
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

    private function isAssociative(
        array $array,
    ): bool {
        if ($array === []) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }
}
