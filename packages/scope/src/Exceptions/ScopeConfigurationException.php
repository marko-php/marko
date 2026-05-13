<?php

declare(strict_types=1);

namespace Marko\Scope\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * Exception thrown when scope configuration is malformed or invalid.
 */
class ScopeConfigurationException extends MarkoException
{
    public static function malformedString(string $scope): self
    {
        return new self(
            message: "Malformed scope string \"$scope\": expected \"axis:path\" format",
            context: "Parsing scope string \"$scope\" via Scope::fromString()",
            suggestion: 'Provide a scope string in the format "axisName:path", e.g. "geo:eu.de"',
        );
    }

    public static function malformedConfig(
        string $axis,
        string $reason,
    ): self {
        return new self(
            message: "Scope configuration for axis '$axis' is malformed: $reason",
            context: "Parsing scope configuration for axis '$axis'",
            suggestion: "Review the scope configuration for axis '$axis' and correct the malformed definition",
        );
    }

    public static function duplicatePath(string $path): self
    {
        return new self(
            message: "Duplicate scope path '$path' declared in hierarchy",
            context: "Building ScopeHierarchy with path '$path' that has already been declared",
            suggestion: "Remove the duplicate declaration of '$path' from the scope configuration",
        );
    }

    /**
     * @param class-string $entityClass
     */
    public static function missingScopesStorage(string $entityClass): self
    {
        return new self(
            message: "Entity '$entityClass' has #[Scoped] properties but provides no scope storage. Add 'use HasScopes; implements HasScopesInterface;' to the entity, or register a companion class that implements HasScopesInterface.",
            context: "Validating scoped entity '$entityClass'",
            suggestion: "Either add 'use HasScopes; implements HasScopesInterface;' to '$entityClass', or create and register a companion class extending Entity that implements HasScopesInterface using the HasScopes trait.",
        );
    }
}
