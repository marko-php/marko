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
}
