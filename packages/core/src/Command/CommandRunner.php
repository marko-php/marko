<?php

declare(strict_types=1);

namespace Marko\Core\Command;

use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\CommandException;

/**
 * Executes commands by name.
 *
 * A command accepts only the options its #[Command] attribute declares (`options` and `flags`) plus the global
 * flags. Any other option fails the run before the command executes, so a typo or an unsupported option such as
 * `--help` on a command that does not expect it never runs the command as if the option were absent. `--help`
 * prints the command's help and never executes it.
 */
class CommandRunner
{
    public function __construct(
        private ContainerInterface $container,
        private CommandRegistry $registry,
    ) {}

    /**
     * @throws CommandException If the command is not found, or is given an option it does not declare.
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

        if ($definition->flags !== []) {
            $input = $input->withFlags($definition->flags);
        }

        if ($input->hasOption(Input::HELP)) {
            $this->writeHelp($definition, $output);

            return 0;
        }

        $undeclaredOptions = $input->undeclaredOptions($definition->acceptedOptions());

        if ($undeclaredOptions !== []) {
            throw CommandException::undeclaredOptions(
                $definition->name,
                $undeclaredOptions,
                $definition->acceptedOptionUsages(),
            );
        }

        // The running command's Input and Output are injectable, so console services such as
        // the confirmation prompter read the same options and write to the same stream.
        $this->container->instance(Input::class, $input);
        $this->container->instance(Output::class, $output);

        /** @var CommandInterface $command */
        $command = $this->container->get($definition->commandClass);

        return $command->execute($input, $output);
    }

    private function writeHelp(
        CommandDefinition $definition,
        Output $output,
    ): void {
        $title = $definition->aliases === []
            ? $definition->name
            : $definition->name . ' (' . implode(', ', $definition->aliases) . ')';

        $output->writeLine($title);

        if ($definition->description !== '') {
            $output->writeLine('  ' . $definition->description);
        }

        if ($definition->destructive) {
            $output->writeLine('  Changes or deletes stored state.');
        }

        $output->writeLine('');
        $output->writeLine('Usage:');
        $output->writeLine("  marko $definition->name [options] [arguments]");
        $output->writeLine('');
        $output->writeLine('Options:');

        foreach ($definition->acceptedOptionUsages() as $usage) {
            $output->writeLine("  $usage");
        }
    }
}
