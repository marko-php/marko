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
class ListCommand implements CommandInterface
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

        // Calculate max name width for alignment
        $maxNameWidth = 0;
        foreach ($commands as $definition) {
            $maxNameWidth = max($maxNameWidth, strlen($definition->name));
        }

        // Add padding
        $nameWidth = $maxNameWidth + 2;

        // Output commands
        foreach ($commands as $definition) {
            $output->writeLine(
                '  ' . str_pad($definition->name, $nameWidth) . $definition->description,
            );
        }

        return 0;
    }
}
