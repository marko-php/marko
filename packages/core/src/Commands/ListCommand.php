<?php

declare(strict_types=1);

namespace Marko\Core\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\CommandRegistry;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

/** @noinspection PhpUnused */
#[Command(name: 'list', description: 'Show all available commands')]
readonly class ListCommand implements CommandInterface
{
    public function __construct(
        private CommandRegistry $registry,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $commands = $this->registry->all();

        if ($commands === []) {
            $output->writeLine('No commands available.');

            return 0;
        }

        // Build display names (name + aliases if present)
        $displayNames = [];
        foreach ($commands as $definition) {
            if ($definition->aliases !== []) {
                $displayNames[$definition->name] = $definition->name . ' (' . implode(', ', $definition->aliases) . ')';
            } else {
                $displayNames[$definition->name] = $definition->name;
            }
        }

        // Calculate max display name width for alignment
        $maxNameWidth = 0;
        foreach ($displayNames as $displayName) {
            $maxNameWidth = max($maxNameWidth, strlen($displayName));
        }

        // Add padding
        $nameWidth = $maxNameWidth + 2;

        // Output commands
        foreach ($commands as $definition) {
            $output->writeLine(
                '  ' . str_pad($displayNames[$definition->name], $nameWidth) . $definition->description,
            );
        }

        return 0;
    }
}
