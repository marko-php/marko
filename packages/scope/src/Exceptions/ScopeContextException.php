<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when scope context cannot be resolved for an axis or path.
 */
class ScopeContextException extends MarkoException
{
    public static function axisNotSet(string $axis): self
    {
        return new self(
            message: "Scope context for axis '$axis' has not been set",
            context: "Reading scope context for axis '$axis'",
            suggestion: "Set the scope context for axis '$axis' before attempting to read it",
        );
    }

    public static function invalidPath(
        string $axis,
        string $path,
    ): self
    {
        return new self(
            message: "Scope context path '$path' is not valid for axis '$axis'",
            context: "Reading scope context path '$path' on axis '$axis'",
            suggestion: "Ensure the path '$path' corresponds to a registered scope on axis '$axis'",
        );
    }

    public static function propertyNotScoped(
        string $property,
        string $entityClass,
    ): self
    {
        return new self(
            message: "Property '$property' on '$entityClass' is not marked as @Scoped",
            context: "Building ScopedOrderBy for property '$property' on '$entityClass'",
            suggestion: "Add #[Scoped(axes: [...])] to the '$property' property on '$entityClass'",
        );
    }

    public static function unknownProperty(
        string $entityClass,
        string $property,
    ): self
    {
        return new self(
            message: "Property '$property' does not exist on entity '$entityClass'",
            context: "Resolving scoped property '$property' on '$entityClass'",
            suggestion: "Ensure the property '$property' is declared on '$entityClass'",
        );
    }
}
