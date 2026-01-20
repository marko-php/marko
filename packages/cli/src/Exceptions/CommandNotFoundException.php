<?php

declare(strict_types=1);

namespace Marko\Cli\Exceptions;

class CommandNotFoundException extends CliException
{
    public static function forCommand(
        string $commandName,
    ): self {
        return new self(
            message: "Command '$commandName' not found.",
            context: "Attempted to run command '$commandName'",
            suggestion: "Run 'marko list' to see all available commands.",
        );
    }
}
