<?php

declare(strict_types=1);

namespace Marko\Core\Exceptions;

class MissingDependencyException extends MarkoException
{
    /**
     * Thrown when an enabled module requires a dependency that is present but disabled.
     */
    public static function dependencyDisabled(
        string $moduleName,
        string $dependencyName,
    ): self {
        return new self(
            message: "Module '$moduleName' requires '$dependencyName' which is present but disabled",
            context: "While resolving dependencies for '$moduleName'",
            suggestion: "Enable '$dependencyName' or remove it from the require list of '$moduleName'",
        );
    }

    /**
     * Thrown when modules cannot be sorted due to soft-ordering (after/before) deadlocks.
     *
     * @param array<string, string[]> $unsortedConstraints Map of module name to unmet ordering constraints
     */
    public static function orderingDeadlock(
        array $unsortedConstraints,
    ): self {
        $parts = [];
        foreach ($unsortedConstraints as $moduleName => $constraints) {
            $constraintList = implode(', ', $constraints);
            $parts[] = "'$moduleName' (unmet: $constraintList)";
        }
        $offenderList = implode('; ', $parts);

        return new self(
            message: "Module ordering deadlock — cannot resolve load order for: $offenderList",
            context: 'While resolving module dependency order using topological sort',
            suggestion: 'Check for conflicting before/after constraints that create an unsatisfiable ordering (e.g. a module declared both after and before the same target)',
        );
    }
}
