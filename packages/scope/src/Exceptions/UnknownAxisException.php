<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when an unknown scope axis is referenced.
 */
class UnknownAxisException extends MarkoException
{
    public static function forAxis(string $axis): self
    {
        return new self(
            message: "Scope axis '$axis' is not registered",
            context: "Attempting to resolve scope axis '$axis'",
            suggestion: "register the '$axis' axis in your scope configuration, or check for a typo in the axis name",
        );
    }
}
