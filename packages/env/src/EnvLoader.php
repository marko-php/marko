<?php

declare(strict_types=1);

namespace Marko\Env;

class EnvLoader
{
    /**
     * Load environment variables from a .env file.
     *
     * The .env file is optional - if it doesn't exist, the application
     * should work with sensible defaults defined in config files.
     *
     * System environment variables take precedence over .env values,
     * allowing production deployments to override via system env vars.
     */
    public function load(
        string $path,
    ): void {
        $file = $path . '/.env';

        if (!is_file($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $this->processLine($line);
        }
    }

    private function processLine(
        string $line,
    ): void {
        $trimmed = trim($line);

        // Skip comments
        if (str_starts_with($trimmed, '#')) {
            return;
        }

        // Skip lines without =
        if (!str_contains($line, '=')) {
            return;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        // Skip empty names
        if ($name === '') {
            return;
        }

        // Remove surrounding quotes if present
        $value = $this->unquote($value);

        // Don't overwrite existing environment variables
        // This allows system env vars to take precedence
        if (isset($_ENV[$name]) || getenv($name) !== false) {
            return;
        }

        $_ENV[$name] = $value;
        putenv("$name=$value");
    }

    /**
     * Remove surrounding quotes from a value.
     *
     * Supports both single and double quotes.
     */
    private function unquote(
        string $value,
    ): string {
        if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
            return $matches[2];
        }

        return $value;
    }
}
