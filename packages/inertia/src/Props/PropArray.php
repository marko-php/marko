<?php

declare(strict_types=1);

namespace Marko\Inertia\Props;

final class PropArray
{
    /**
     * @param  array<string, mixed>  $props
     */
    public static function set(
        array &$props,
        string $key,
        mixed $value,
    ): void {
        if ($key === '' || ! str_contains($key, '.')) {
            $props[$key] = $value;

            return;
        }

        $segments = array_values(array_filter(
            explode('.', $key),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return;
        }

        $current = &$props;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                $current[$segment] = $value;

                return;
            }

            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }
}
