<?php

declare(strict_types=1);

namespace Marko\Core\Command;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\CommandException;

/**
 * Executes commands by name.
 */
class CommandRunner
{
    public function __construct(
        private ContainerInterface $container,
        private CommandRegistry $registry,
    ) {}

    /**
     * @throws CommandException If the command is not found.
     */
    public function run(
        string $commandName,
        Input $input,
        Output $output,
    ): int {
        $definition = $this->registry->get($commandName);

        if ($definition === null) {
            throw CommandException::commandNotFound($commandName);
        }

        /** @var CommandInterface $command */
        $command = $this->container->get($definition->commandClass);

        return $command->execute($input, $output);
    }
}
