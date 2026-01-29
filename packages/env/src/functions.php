<?php

declare(strict_types=1);

if (!function_exists('env')) {
    /**
     * Get an environment variable with optional default and type coercion.
     *
     * Type coercion handles common string representations:
     * - 'true', '(true)' → true
     * - 'false', '(false)' → false
     * - 'null', '(null)' → null
     * - 'empty', '(empty)' → ''
     *
     * @param string $key The environment variable name
     * @param mixed $default Default value if the variable is not set
     * @return mixed The environment variable value (with type coercion) or default
     */
    function env(
        string $key,
        mixed $default = null,
    ): mixed {
        // Check $_ENV first (populated by EnvLoader), then getenv() as fallback
        $value = $_ENV[$key] ?? null;

        if ($value === null) {
            $envValue = getenv($key);
            $value = $envValue === false ? null : $envValue;
        }

        if ($value === null) {
            return $default;
        }

        // Type coercion for common string patterns
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
