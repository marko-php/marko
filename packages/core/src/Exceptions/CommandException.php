<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class CommandException extends MarkoException
{
    public static function missingExecuteMethod(
        string $class,
    ): self {
        return new self(
            message: "Command '$class' must have an execute method",
            context: "While validating command '$class'",
            suggestion: 'Add a public execute(Input $input, Output $output): int method',
        );
    }

    public static function doesNotImplementInterface(
        string $class,
    ): self {
        return new self(
            message: "Command '$class' must implement CommandInterface",
            context: "While validating command '$class'",
            suggestion: 'Add "implements CommandInterface" to your command class',
        );
    }

    public static function duplicateCommandName(
        string $name,
    ): self {
        return new self(
            message: "Command '$name' is already registered",
            context: "While registering command '$name'",
            suggestion: 'Ensure each command has a unique name. If you need to override an existing command, use a Preference on the command class instead.',
        );
    }

    /**
     * @param list<string> $undeclaredOptions The refused options, as typed (`--dryrun`, `-x`)
     * @param list<string> $acceptedOptions The options the command accepts, as typed (`--queue=<value>`, `--force`)
     */
    public static function undeclaredOptions(
        string $name,
        array $undeclaredOptions,
        array $acceptedOptions,
    ): self {
        $noun = count($undeclaredOptions) === 1 ? 'option' : 'options';
        $quoted = implode(', ', array_map(fn (string $option): string => "'$option'", $undeclaredOptions));

        return new self(
            message: "Command '$name' does not accept the $noun $quoted",
            context: "While checking the options given to command '$name', before running it",
            suggestion: 'It accepts: ' . implode(', ', $acceptedOptions) . ". Run 'marko $name --help' for usage."
                . ' If the command reads this option, declare it in #[Command(options: [...])], or in flags: [...]'
                . ' when it takes no value.',
        );
    }

    public static function commandNotFound(
        string $name,
    ): self {
        return new self(
            message: "Command '$name' not found",
            context: "While looking up command '$name'",
            suggestion: 'Run the list command to see available commands.',
        );
    }
}
