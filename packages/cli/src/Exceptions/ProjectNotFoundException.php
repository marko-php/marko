<?php

declare(strict_types=1);

namespace Marko\Cli\Exceptions;

class ProjectNotFoundException extends CliException
{
    public static function fromDirectory(
        string $directory,
    ): self {
        return new self(
            message: "No Marko project found in '$directory' or any parent directory.",
            context: "Current directory: $directory",
            suggestion: "Make sure you're running this command from within a Marko project (a directory containing vendor/marko/core).",
        );
    }
}
