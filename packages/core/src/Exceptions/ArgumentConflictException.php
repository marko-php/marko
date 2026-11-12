<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class ArgumentConflictException extends MarkoException
{
    /**
     * @param array<int, string> $modules
     */
    public static function multipleModules(
        string $class,
        string $parameter,
        array $modules,
    ): self {
        $moduleList = implode(', ', $modules);

        return new self(
            message: "Multiple modules set the argument '\$$parameter' of '$class' at the same priority: $moduleList",
            context: "While merging the 'arguments' entries for '$class'",
            suggestion: "Set '\$$parameter' in a higher-priority module to resolve the conflict, or remove the duplicate entry",
        );
    }
}
