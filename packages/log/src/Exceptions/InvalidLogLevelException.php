<?php

declare(strict_types=1);

namespace Marko\Log\Exceptions;

class InvalidLogLevelException extends LogException
{
    public static function forLevel(
        string $level,
    ): self {
        $validLevels = 'emergency, alert, critical, error, warning, notice, info, debug';

        return new self(
            message: "Invalid log level '$level'",
            context: "Requested level: $level",
            suggestion: "Use one of the valid log levels: $validLevels",
        );
    }
}
