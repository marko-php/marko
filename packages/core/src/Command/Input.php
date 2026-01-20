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
}
