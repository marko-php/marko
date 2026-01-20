<?php

declare(strict_types=1);

namespace Marko\Core\Command;

use Marko\Core\Exceptions\CommandException;

/**
 * Stores commands indexed by name.
 */
class CommandRegistry
{
    /** @var array<string, CommandDefinition> */
    private array $commands = [];

    /**
     * Register a command definition.
     *
     * @throws CommandException If a command with the same name is already registered.
     */
    public function register(
        CommandDefinition $definition,
    ): void {
        if ($this->has($definition->name)) {
            throw CommandException::duplicateCommandName($definition->name);
        }

        $this->commands[$definition->name] = $definition;
    }

    public function get(
        string $name,
    ): ?CommandDefinition {
        return $this->commands[$name] ?? null;
    }

    /**
     * Check if a command exists by name.
     */
    public function has(
        string $name,
    ): bool {
        return isset($this->commands[$name]);
    }

    /**
     * Get all registered commands sorted alphabetically by name.
     *
     * @return array<CommandDefinition>
     */
    public function all(): array
    {
        $commands = $this->commands;
        ksort($commands);

        return array_values($commands);
    }
}
