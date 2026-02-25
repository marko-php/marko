<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Parses and provides access to command-line arguments.
 */
readonly class Input
{
    /**
     * @param array<int, string> $arguments The raw command-line arguments (argv-style)
     */
    public function __construct(
        private array $arguments,
    ) {}

    /**
     * Returns the command name (second argument after script name).
     */
    public function getCommand(): ?string
    {
        return $this->arguments[1] ?? null;
    }

    /**
     * Returns arguments after the command name (index 0 = script, index 1 = command).
     *
     * @return array<int, string>
     */
    public function getArguments(): array
    {
        return array_values(array_slice($this->arguments, 2));
    }

    /**
     * Checks if an argument exists at the given index (relative to arguments after command).
     */
    public function hasArgument(
        int $index,
    ): bool {
        return isset($this->getArguments()[$index]);
    }

    /**
     * Returns the argument at the given index, or null if not found.
     */
    public function getArgument(
        int $index,
    ): ?string {
        return $this->getArguments()[$index] ?? null;
    }

    /**
     * Checks if an option exists (e.g., --verbose, --queue=value, -d, -p=8000).
     *
     * Single-char names match short options (-x), multi-char names match long options (--name).
     */
    public function hasOption(
        string $name,
    ): bool {
        if (strlen($name) === 1) {
            return array_any(
                $this->getArguments(),
                fn ($arg) => $arg === "-$name" || str_starts_with($arg, "-$name="),
            );
        }

        return array_any(
            $this->getArguments(),
            fn ($arg) => $arg === "--$name" || str_starts_with($arg, "--$name="),
        );
    }

    /**
     * Returns the value of an option, or null if not found.
     * For boolean flags (--verbose, -d), returns "true".
     * For value options (--queue=emails, -p=8000, -p 8000), returns the value.
     *
     * Single-char names match short options, multi-char names match long options.
     */
    public function getOption(
        string $name,
    ): ?string {
        $arguments = $this->getArguments();

        if (strlen($name) === 1) {
            foreach ($arguments as $index => $arg) {
                if ($arg === "-$name") {
                    $next = $arguments[$index + 1] ?? null;
                    if ($next !== null && !str_starts_with($next, '-')) {
                        return $next;
                    }

                    return 'true';
                }
                if (str_starts_with($arg, "-$name=")) {
                    return substr($arg, strlen("-$name="));
                }
            }

            return null;
        }

        foreach ($arguments as $arg) {
            if ($arg === "--$name") {
                return 'true';
            }
            if (str_starts_with($arg, "--$name=")) {
                return substr($arg, strlen("--$name="));
            }
        }

        return null;
    }
}
