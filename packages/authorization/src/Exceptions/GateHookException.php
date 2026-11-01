<?php

declare(strict_types=1);

namespace Marko\Authorization\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * A #[GateHook] class is declared incorrectly.
 */
class GateHookException extends MarkoException
{
    public static function notAHook(
        string $className,
    ): self {
        return new self(
            message: "Gate hook '$className' does not implement GateHookInterface",
            context: "'$className' is marked with #[GateHook], so the Gate would call its before() method",
            suggestion: "Implement Marko\\Authorization\\Contracts\\GateHookInterface on '$className', or remove the #[GateHook] attribute",
        );
    }
}
