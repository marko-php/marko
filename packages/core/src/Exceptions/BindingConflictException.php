<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class BindingConflictException extends MarkoException
{
    /**
     * @param string[] $modules
     */
    public static function multipleBindings(
        string $interface,
        array $modules,
    ): self {
        $moduleList = implode(', ', $modules);

        return new self(
            message: "Multiple modules bind the same interface '$interface': $moduleList",
            context: "While loading module bindings for '$interface'",
            suggestion: 'Use a Preference in a higher-priority module to resolve the conflict, or remove duplicate bindings',
        );
    }
}
