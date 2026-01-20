<?php

declare(strict_types=1);

namespace Marko\Cli\Exceptions;

use Throwable;

class BootstrapException extends CliException
{
    public static function fromCause(
        Throwable $cause,
    ): self {
        return new self(
            message: "Failed to boot Marko application: {$cause->getMessage()}",
            context: 'While bootstrapping the application',
            suggestion: 'Check your project configuration and try again.',
            previous: $cause,
        );
    }
}
